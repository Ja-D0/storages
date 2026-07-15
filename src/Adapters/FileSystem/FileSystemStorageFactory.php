<?php

namespace JaD0\Storages\Adapters\FileSystem;

use JaD0\Storages\Exceptions\StorageException;
use JaD0\Storages\Interfaces\Storage;
use JaD0\Storages\Interfaces\StorageFactory;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Фабрика локальных файловых хранилищ, создающая FileSystemStorage внутри заданного корня.
 */
class FileSystemStorageFactory implements StorageFactory
{
    /**
     * @var string
     */
    protected $rootDir;

    /**
     * @var LoggerInterface|null
     */
    protected $logger;

    /**
     * Конструктор.
     *
     * @param string $rootDir
     * @param LoggerInterface|null $logger
     */
    public function __construct(string $rootDir, ?LoggerInterface $logger = null)
    {
        $this->rootDir = $rootDir;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     * @throws StorageException
     */
    public function createStorage(string $identifier): Storage
    {
        $fullPath = $this->rootDir . DIRECTORY_SEPARATOR . $identifier;

        return new FileSystemStorage($fullPath, $this->logger ?? new NullLogger());
    }
}
