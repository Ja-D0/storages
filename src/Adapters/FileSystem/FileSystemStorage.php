<?php

namespace JaD0\Storages\Adapters\FileSystem;

use Exception;
use GuzzleHttp\Psr7\Stream;
use JaD0\Storages\Dto\ListedObject;
use JaD0\Storages\Exceptions\StorageObjectNotFoundException;
use JaD0\Storages\Exceptions\StorageException;
use JaD0\Storages\Interfaces\Storage;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use UnexpectedValueException;

/**
 * Адаптер Storage для локальной файловой системы, использующий директорию как корень хранилища.
 *
 * Чтение и копирование отсутствующего исходного объекта завершаются
 * StorageObjectNotFoundException. Ошибки доступа и ввода-вывода остаются
 * техническими StorageException.
 */
class FileSystemStorage implements Storage
{
    /**
     * @var string
     */
    protected $basePath;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Конструктор.
     *
     * @param string $basePath Абсолютный путь к корню (папке/контейнеру)
     * @param LoggerInterface $logger
     * @throws StorageException
     */
    public function __construct(string $basePath, LoggerInterface $logger)
    {
        $this->basePath = $basePath;
        $this->logger = $logger;

        if (!is_dir($this->basePath) && !@mkdir($this->basePath, 0775, true) && !is_dir($this->basePath)) {
            $message = "Не удалось создать директорию хранилища: $this->basePath";
            $this->logger->error($message, ["category" => "storage.FileSystemStorage"]);

            throw new StorageException($message);
        }
    }

    /**
     * @inheritDoc
     */
    public function exists(string $path): bool
    {
        if (!is_dir($this->basePath) || !is_readable($this->basePath)) {
            $message = "Корневая директория хранилища недоступна: $this->basePath";
            $this->logger->error($message, ["category" => "storage.FileSystemStorage"]);

            throw new StorageException($message);
        }

        $fullPath = $this->getFullPath($path);

        if (file_exists($fullPath)) {
            return true;
        }

        $existingParent = dirname($fullPath);
        while (!is_dir($existingParent) && dirname($existingParent) !== $existingParent) {
            $existingParent = dirname($existingParent);
        }

        if (!is_dir($existingParent) || !is_readable($existingParent)) {
            $message = "Не удалось достоверно проверить наличие файла: $path";
            $this->logger->error($message, ["category" => "storage.FileSystemStorage"]);

            throw new StorageException($message);
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function copy(string $srcPath, string $destPath): void
    {
        $fullSrc = $this->getFullPath($srcPath);
        $fullDest = $this->getFullPath($destPath);

        if (!$this->exists($srcPath)) {
            $message = "Исходный файл не найден: $srcPath";
            $this->logger->error($message, ["category" => "storage.FileSystemStorage"]);

            throw new StorageObjectNotFoundException($srcPath);
        }

        $this->ensureDirectoryExists(dirname($fullDest));

        if (!@copy($fullSrc, $fullDest)) {
            $message = "Не удалось скопировать файл из $srcPath в $destPath";
            $this->logger->error($message, ["category" => "storage.FileSystemStorage"]);

            throw new StorageException($message);
        }
    }

    /**
     * @inheritDoc
     */
    public function push(string $localPath, string $storagePath): void
    {
        if (!file_exists($localPath)) {
            $message = "Локальный файл не найден: $localPath";
            $this->logger->error($message, ["category" => "storage.FileSystemStorage"]);

            throw new StorageException($message);
        }

        $fullStoragePath = $this->getFullPath($storagePath);

        if (file_exists($fullStoragePath) && realpath($localPath) === realpath($fullStoragePath)) {
            return;
        }

        $this->ensureDirectoryExists(dirname($fullStoragePath));

        if (!@copy($localPath, $fullStoragePath)) {
            $message = "Не удалось загрузить файл в $storagePath";
            $this->logger->error($message, ["category" => "storage.FileSystemStorage"]);

            throw new StorageException($message);
        }
    }

    /**
     * @inheritDoc
     */
    public function remove(string $path): void
    {
        $fullPath = $this->getFullPath($path);
        if ($this->exists($path) && !@unlink($fullPath)) {
            $message = "Не удалось удалить файл: $path";
            $this->logger->error($message, ["category" => "storage.FileSystemStorage"]);

            throw new StorageException($message);
        }
    }

    /**
     * @inheritDoc
     */
    public function readAsStream(string $path): StreamInterface
    {
        if (!$this->exists($path)) {
            $message = "Файл не найден для чтения: $path";
            $this->logger->error($message, ["category" => "storage.FileSystemStorage"]);

            throw new StorageObjectNotFoundException($path);
        }

        $resource = @fopen($this->getFullPath($path), 'rb');

        if (!$resource) {
            $message = "Не удалось открыть поток на чтение: $path";
            $this->logger->error($message, ["category" => "storage.FileSystemStorage"]);

            throw new StorageException($message);
        }

        return new Stream($resource);
    }

    /**
     * @inheritDoc
     */
    public function listObjects(string $prefix = ""): iterable
    {
        $prefix = $this->normalizeStoragePath($prefix);

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->basePath, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $fileInfo) {
                if (!$fileInfo->isFile()) {
                    continue;
                }

                $path = $this->normalizeStoragePath(substr($fileInfo->getPathname(), strlen($this->basePath)));

                if ($prefix !== '' && strpos($path, $prefix) !== 0) {
                    continue;
                }

                yield new ListedObject(
                    $path,
                    $fileInfo->getBasename(),
                    $fileInfo->getSize(),
                    $fileInfo->getMTime()
                );
            }
        } catch (UnexpectedValueException $exception) {
            $message = "Не удалось получить список объектов с префиксом '$prefix': " . $exception->getMessage();
            $this->logger->error(
                $message,
                ["category" => "storage.FileSystemStorage", "exception" => $exception]
            );

            throw new StorageException($message, false, $exception);
        }
    }

    /**
     * @inheritDoc
     */
    public function writeStream(StreamInterface $stream, string $storagePath): void
    {
        $fullPath = $this->getFullPath($storagePath);
        $this->ensureDirectoryExists(dirname($fullPath));

        $destResource = @fopen($fullPath, 'wb');
        if (!$destResource) {
            $message = "Не удалось открыть файл на запись: $storagePath";
            $this->logger->error($message, ["category" => "storage.FileSystemStorage"]);
            throw new StorageException($message);
        }

        $destStream = new Stream($destResource);

        try {
            while (!$stream->eof()) {
                $destStream->write($stream->read(8192));
            }
        } catch (Exception $e) {
            $message = "Ошибка при записи потока в $storagePath: " . $e->getMessage();
            $this->logger->error($message, ["category" => "storage.FileSystemStorage", "exception" => $e]);
            throw new StorageException($message, false, $e);
        } finally {
            $destStream->close();
        }
    }

    /**
     * Формирует полный путь с учетом базовой директории.
     *
     * @param string $path
     * @return string
     */
    protected function getFullPath(string $path): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
    }

    /**
     * Нормализует путь хранилища к формату с разделителем "/".
     *
     * @param string $path
     * @return string
     */
    protected function normalizeStoragePath(string $path): string
    {
        return trim(str_replace(DIRECTORY_SEPARATOR, '/', $path), '/');
    }

    /**
     * Создает рекурсивно директории, если они не существуют.
     *
     * @param string $directory
     * @return void
     * @throws StorageException
     */
    protected function ensureDirectoryExists(string $directory): void
    {
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            $message = "Не удалось создать директорию: $directory";
            $this->logger->error($message, ["category" => "storage.FileSystemStorage"]);

            throw new StorageException($message);
        }
    }
}
