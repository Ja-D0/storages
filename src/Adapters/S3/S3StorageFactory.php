<?php

namespace JaD0\Storages\Adapters\S3;

use Aws\S3\S3Client;
use JaD0\Storages\Interfaces\Storage;
use JaD0\Storages\Interfaces\StorageFactory;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Фабрика S3-хранилищ, создающая Storage для конкретного бакета.
 */
final class S3StorageFactory implements StorageFactory
{
    /**
     * @var S3Client
     */
    protected $client;

    /**
     * @var LoggerInterface|null
     */
    protected $logger;

    /**
     * Конструктор.
     *
     * @param S3Client $client Инициализированный клиент S3
     * @param LoggerInterface|null $logger
     */
    public function __construct(S3Client $client, ?LoggerInterface $logger = null)
    {
        $this->client = $client;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function createStorage(string $identifier): Storage
    {
        return new S3Storage($this->client, $identifier, $this->logger ?? new NullLogger());
    }
}
