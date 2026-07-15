<?php

namespace JaD0\Storages\Adapters\Swift;

use JaD0\Storages\Interfaces\Storage;
use JaD0\Storages\Interfaces\StorageFactory;
use OpenStack\OpenStack;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Фабрика Swift-хранилищ, создающая Storage для конкретного контейнера.
 */
final class SwiftStorageFactory implements StorageFactory
{
    /**
     * @var OpenStack
     */
    protected $openStack;

    /**
     * @var LoggerInterface|null
     */
    protected $logger;

    /**
     * Конструктор.
     *
     * @param OpenStack $openStack Инициализированное подключение OpenStack
     * @param LoggerInterface|null $logger
     */
    public function __construct(OpenStack $openStack, ?LoggerInterface $logger = null)
    {
        $this->openStack = $openStack;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function createStorage(string $identifier): Storage
    {
        return new SwiftStorage($this->openStack, $identifier, $this->logger ?? new NullLogger());
    }
}
