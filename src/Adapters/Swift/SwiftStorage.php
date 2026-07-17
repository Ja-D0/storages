<?php

namespace JaD0\Storages\Adapters\Swift;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Stream;
use JaD0\Storages\Dto\ListedObject;
use JaD0\Storages\Exceptions\StorageObjectNotFoundException;
use JaD0\Storages\Exceptions\StorageException;
use JaD0\Storages\Exceptions\StorageUnavailableException;
use JaD0\Storages\Interfaces\Storage;
use JaD0\Storages\Support\MimeTypeResolver;
use OpenStack\Common\Error\BadResponseError;
use OpenStack\ObjectStore\v1\Models\Container;
use OpenStack\OpenStack;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;

/**
 * Адаптер Storage для OpenStack Swift/Object Store хранилища.
 */
class SwiftStorage implements Storage
{
    private const DEFAULT_MIME_TYPE = "application/octet-stream";

    /**
     * @var OpenStack
     */
    protected $openStack;

    /**
     * @var string
     */
    protected $containerName;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Container|null
     */
    private $container;

    /**
     * Конструктор.
     *
     * @param OpenStack $openStack
     * @param string $containerName
     * @param LoggerInterface $logger
     */
    public function __construct(
        OpenStack $openStack,
        string $containerName,
        LoggerInterface $logger
    )
    {
        $this->openStack = $openStack;
        $this->containerName = $containerName;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function exists(string $path): bool
    {
        try {
            return $this->getContainerForObjectStore()->objectExists($path);
        } catch (BadResponseError $badResponseError) {
            if ($this->isObjectNotFound($badResponseError)) {
                return false;
            }

            $this->logger->error(
                "Ошибка проверки существования объекта '$path' в контейнере '$this->containerName': "
                . $badResponseError->getMessage(),
                ["category" => "storage.SwiftStorage", "exception" => $badResponseError]
            );
            $this->handleBadResponseError($badResponseError);
        } catch (ConnectException $connectException) {
            $this->logger->error(
                "Ошибка проверки существования объекта '$path' в контейнере '$this->containerName': "
                . $connectException->getMessage(),
                ["category" => "storage.SwiftStorage", "exception" => $connectException]
            );

            throw new StorageUnavailableException($connectException->getMessage(), $connectException);
        }
    }

    /**
     * @inheritDoc
     */
    public function copy(string $srcPath, string $destPath): void
    {
        try {
            $container = $this->getContainerForObjectStore();

            $containerName = $container->name;
            $container->getObject($srcPath)->copy(["destination" => "$containerName/$destPath"]);
        } catch (BadResponseError $badResponseError) {
            if ($this->isObjectNotFound($badResponseError)) {
                throw new StorageObjectNotFoundException($srcPath, $badResponseError);
            }

            $this->logger->error(
                "Ошибка копирования объекта '$srcPath' -> '$destPath' в контейнере '$this->containerName': "
                . $badResponseError->getMessage(),
                ["category" => "storage.SwiftStorage", "exception" => $badResponseError]
            );

            $this->handleBadResponseError($badResponseError);
        } catch (ConnectException $connectException) {
            $this->logger->error(
                "Ошибка копирования объекта '$srcPath' -> '$destPath' в контейнере '$this->containerName': "
                . $connectException->getMessage(),
                ["category" => "storage.SwiftStorage", "exception" => $connectException]
            );

            throw new StorageUnavailableException($connectException->getMessage(), $connectException);
        }
    }

    /**
     * @inheritDoc
     */
    public function push(string $localPath, string $storagePath): void
    {
        $resource = @fopen($localPath, "rb");

        if (!$resource) {
            $message = "Не удалось открыть файл на чтение: $localPath";
            $this->logger->error($message, ["category" => "storage.SwiftStorage"]);

            throw new StorageException($message);
        }

        $stream = new Stream($resource);

        try {
            $this->getContainerForObjectStore()->createObject([
                "name" => $storagePath,
                "stream" => $stream,
                "headers" => [
                    "Content-Type" => MimeTypeResolver::resolve($localPath, self::DEFAULT_MIME_TYPE),
                ]
            ]);
        } catch (BadResponseError $badResponseError) {
            $this->logger->error(
                "Ошибка при пуше файла '$localPath' -> '$storagePath' в контейнер '$this->containerName': "
                . $badResponseError->getMessage(),
                ["category" => "storage.SwiftStorage", "exception" => $badResponseError]
            );

            $this->handleBadResponseError($badResponseError);
        } catch (ConnectException $connectException) {
            $this->logger->error(
                "Ошибка при пуше файла '$localPath' -> '$storagePath' в контейнер '$this->containerName': "
                . $connectException->getMessage(),
                ["category" => "storage.SwiftStorage", "exception" => $connectException]
            );

            throw new StorageUnavailableException($connectException->getMessage(), $connectException);
        } finally {
            $stream->close();
        }
    }

    /**
     * @inheritDoc
     */
    public function remove(string $path): void
    {
        try {
            $this->getContainerForObjectStore()->getObject($path)->delete();
        } catch (ConnectException $connectException) {
            $this->logger->error(
                "Ошибка при удалении объекта '$path' из контейнера '$this->containerName': "
                . $connectException->getMessage(),
                ["category" => "storage.SwiftStorage", "exception" => $connectException]
            );

            throw new StorageUnavailableException($connectException->getMessage(), $connectException);
        } catch (BadResponseError $badResponseError) {
            if ($badResponseError->getResponse()->getStatusCode() === 404) {
                return;
            }

            $this->logger->error(
                "Ошибка при удалении объекта '$path' из контейнера '$this->containerName': "
                . $badResponseError->getMessage(),
                ["category" => "storage.SwiftStorage", "exception" => $badResponseError]
            );

            $this->handleBadResponseError($badResponseError);
        }
    }

    /**
     * @inheritDoc
     */
    public function readAsStream(string $path): StreamInterface
    {
        try {
            return $this->getContainerForObjectStore()->getObject($path)->download();
        } catch (BadResponseError $badResponseError) {
            if ($this->isObjectNotFound($badResponseError)) {
                throw new StorageObjectNotFoundException($path, $badResponseError);
            }

            $this->logger->error(
                "Ошибка при чтении объекта '$path' в поток в контейнере '$this->containerName': "
                . $badResponseError->getMessage(),
                ["category" => "storage.SwiftStorage", "exception" => $badResponseError]
            );

            $this->handleBadResponseError($badResponseError);
        } catch (ConnectException $connectException) {
            $this->logger->error(
                "Ошибка при чтении объекта '$path' в поток в контейнере '$this->containerName': "
                . $connectException->getMessage(),
                ["category" => "storage.SwiftStorage", "exception" => $connectException]
            );

            throw new StorageUnavailableException($connectException->getMessage(), $connectException);
        }
    }

    /**
     * @inheritDoc
     */
    public function listObjects(string $prefix = ""): iterable
    {
        try {
            $objects = $this->getContainerForObjectStore()->listObjects(['prefix' => $prefix]);

            foreach ($objects as $object) {
                $path = (string)($object->name ?? '');

                if ($path === '') {
                    continue;
                }

                yield new ListedObject(
                    $path,
                    basename($path),
                    isset($object->contentLength) ? (int)$object->contentLength : null,
                    $object->lastModified ?? null
                );
            }
        } catch (BadResponseError $badResponseError) {
            $this->logger->error(
                "Ошибка получения списка объектов с префиксом '$prefix' в контейнере '$this->containerName': "
                . $badResponseError->getMessage(),
                ["category" => "storage.SwiftStorage", "exception" => $badResponseError]
            );

            $this->handleBadResponseError($badResponseError);
        } catch (ConnectException $connectException) {
            $this->logger->error(
                "Ошибка получения списка объектов с префиксом '$prefix' в контейнере '$this->containerName': "
                . $connectException->getMessage(),
                ["category" => "storage.SwiftStorage", "exception" => $connectException]
            );

            throw new StorageUnavailableException($connectException->getMessage(), $connectException);
        }
    }

    /**
     * @inheritDoc
     */
    public function writeStream(StreamInterface $stream, string $storagePath): void
    {
        $uri = $stream->getMetadata("uri");

        try {
            $this->getContainerForObjectStore()->createObject([
                "name" => $storagePath,
                "stream" => $stream,
                "headers" => [
                    "Content-Type" => !empty($uri)
                        ? MimeTypeResolver::resolve($uri, self::DEFAULT_MIME_TYPE)
                        : self::DEFAULT_MIME_TYPE,
                ]
            ]);
        } catch (BadResponseError $badResponseError) {
            $this->logger->error(
                "Ошибка записи потока данных в контейнер '$this->containerName' в путь '$storagePath': "
                . $badResponseError->getMessage(),
                ["category" => "storage.SwiftStorage", "exception" => $badResponseError]
            );

            $this->handleBadResponseError($badResponseError);
        } catch (ConnectException $connectException) {
            $this->logger->error(
                "Ошибка записи потока данных в контейнер '$this->containerName' в путь '$storagePath': "
                . $connectException->getMessage(),
                ["category" => "storage.SwiftStorage", "exception" => $connectException]
            );

            throw new StorageUnavailableException($connectException->getMessage(), $connectException);
        }
    }

    /**
     * Получить экземпляр Container API для работы с объектами в хранилище
     *
     * @return Container
     * @throws BadResponseError
     * @throws ConnectException
     */
    protected function getContainerForObjectStore(): Container
    {
        if (empty($this->container)) {
            try {
                $this->container = $this->openStack->objectStoreV1()->getContainer($this->containerName);
            } catch (ConnectException|BadResponseError $exception) {
                $this->logger->error(
                    "Ошибка инициализации Container API для контейнера $this->containerName: "
                    . $exception->getMessage(),
                    ["category" => "storage.SwiftStorage", "exception" => $exception]
                );

                throw $exception;
            }
        }

        return $this->container;
    }

    /**
     * Обрабатывает исключение неуспешного ответа от хранилища.
     * Все ответы со статусом >= 500 считаются ответами недоступности хранилища.
     *
     * @param BadResponseError $badResponseError
     * @return void
     * @throws StorageException
     */
    protected function handleBadResponseError(BadResponseError $badResponseError): void
    {
        $isNotAvailable = $badResponseError->getResponse()->getStatusCode() >= 500;

        if ($isNotAvailable) {
            throw new StorageUnavailableException($badResponseError->getMessage(), $badResponseError);
        }

        throw new StorageException($badResponseError->getMessage(), $isNotAvailable, $badResponseError);
    }

    /**
     * Проверяет, что Swift достоверно сообщил об отсутствии объекта.
     *
     * @param BadResponseError $badResponseError
     * @return bool
     * @throws StorageException в случае отсутствия или ошибки проверки контейнера
     */
    private function isObjectNotFound(BadResponseError $badResponseError): bool
    {
        if ($badResponseError->getResponse()->getStatusCode() !== 404) {
            return false;
        }

        return true;
    }
}
