<?php

namespace JaD0\Storages\Adapters\S3;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use JaD0\Storages\Dto\ListedObject;
use JaD0\Storages\Exceptions\StorageException;
use JaD0\Storages\Interfaces\Storage;
use JaD0\Storages\Support\MimeTypeResolver;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;

/**
 * Адаптер Storage для S3-совместимого объектного хранилища.
 */
class S3Storage implements Storage
{
    private const DEFAULT_MIME_TYPE = "application/octet-stream";

    /**
     * @var S3Client
     */
    protected $client;

    /**
     * @var string
     */
    protected $bucket;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Конструктор.
     *
     * @param S3Client $client Инициализированный клиент S3
     * @param string $bucket Название бакета
     * @param LoggerInterface $logger
     */
    public function __construct(
        S3Client $client,
        string $bucket,
        LoggerInterface $logger
    )
    {
        $this->client = $client;
        $this->bucket = $bucket;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function exists(string $path): bool
    {
        try {
            return $this->client->doesObjectExistV2($this->bucket, $path);
        } catch (S3Exception $exception) {
            $this->logger->error(
                "Ошибка при проверке существования объекта '$path' в бакете '$this->bucket': " . $exception,
                ["category" => "storage.S3Storage", "exception" => $exception]
            );

            throw new StorageException($exception->getMessage(), $exception->isConnectionError(), $exception);
        }
    }

    /**
     * @inheritDoc
     */
    public function copy(string $srcPath, string $destPath): void
    {
        try {
            $this->client->copyObject([
                'Bucket' => $this->bucket,
                'Key' => $destPath,
                'CopySource' => "$this->bucket/$srcPath",
            ]);
        } catch (S3Exception $exception) {
            $this->logger->error(
                "Ошибка при копировании объекта '$srcPath' -> '$destPath' в бакете '$this->bucket':" . $exception,
                ["category" => "storage.S3Storage", "exception" => $exception]
            );

            throw new StorageException($exception->getMessage(), $exception->isConnectionError(), $exception);
        }
    }

    /**
     * @inheritDoc
     */
    public function push(string $localPath, string $storagePath): void
    {
        try {
            $this->client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $storagePath,
                'SourceFile' => $localPath,
                'ContentType' => MimeTypeResolver::resolve($localPath, self::DEFAULT_MIME_TYPE),
            ]);
        } catch (S3Exception $exception) {
            $this->logger->error(
                "Ошибка при пуше файла '$localPath' -> '$storagePath' в бакет '$this->bucket': " . $exception,
                ["category" => "storage.S3Storage", "exception" => $exception]
            );

            throw new StorageException($exception->getMessage(), $exception->isConnectionError(), $exception);
        }
    }

    /**
     * @inheritDoc
     */
    public function remove(string $path): void
    {
        try {
            $this->client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $path,
            ]);
        } catch (S3Exception $exception) {
            $this->logger->error(
                "Ошибка при удалении объекта '$path' из бакета '$this->bucket': " . $exception,
                ["category" => "storage.S3Storage", "exception" => $exception]
            );

            throw new StorageException($exception->getMessage(), $exception->isConnectionError(), $exception);
        }
    }

    /**
     * @inheritDoc
     */
    public function readAsStream(string $path): StreamInterface
    {
        try {
            $result = $this->client->getObject([
                'Bucket' => $this->bucket,
                'Key' => $path,
            ]);

            return $result['Body'];
        } catch (S3Exception $exception) {
            $this->logger->error(
                "Ошибка при чтении объекта '$path' в поток в бакете '$this->bucket': " . $exception,
                ["category" => "storage.S3Storage", "exception" => $exception]
            );

            throw new StorageException($exception->getMessage(), $exception->isConnectionError(), $exception);
        }
    }

    /**
     * @inheritDoc
     */
    public function listObjects(string $prefix = ""): iterable
    {
        try {
            $paginator = $this->client->getPaginator('ListObjectsV2', [
                'Bucket' => $this->bucket,
                'Prefix' => $prefix,
            ]);

            foreach ($paginator as $result) {
                foreach ($result['Contents'] ?? [] as $object) {
                    $path = (string)($object['Key'] ?? '');

                    if ($path === '') {
                        continue;
                    }

                    yield new ListedObject(
                        $path,
                        basename($path),
                        isset($object['Size']) ? (int)$object['Size'] : null,
                        $object['LastModified'] ?? null
                    );
                }
            }
        } catch (S3Exception $exception) {
            $this->logger->error(
                "Ошибка при получении списка объектов с префиксом '$prefix' в бакете '$this->bucket': " . $exception,
                ["category" => "storage.S3Storage", "exception" => $exception]
            );

            throw new StorageException($exception->getMessage(), $exception->isConnectionError(), $exception);
        }
    }

    /**
     * @inheritDoc
     */
    public function writeStream(StreamInterface $stream, string $storagePath): void
    {
        $uri = $stream->getMetadata("uri");

        try {
            $this->client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $storagePath,
                'Body' => $stream,
                'ContentType' => !empty($uri)
                    ? MimeTypeResolver::resolve($uri, self::DEFAULT_MIME_TYPE)
                    : self::DEFAULT_MIME_TYPE,
            ]);
        } catch (S3Exception $exception) {
            $this->logger->error(
                "Ошибка записи потока данных в бакет '$this->bucket' в путь '$storagePath': " . $exception,
                ["category" => "storage.S3Storage", "exception" => $exception]
            );

            throw new StorageException($exception->getMessage(), $exception->isConnectionError(), $exception);
        }
    }
}
