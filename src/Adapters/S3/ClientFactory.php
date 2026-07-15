<?php

namespace JaD0\Storages\Adapters\S3;

use Aws\S3\S3Client;
use JaD0\Storages\Exceptions\StorageClientConfigurationException;

/**
 * Контракт фабрики S3 клиента из конфигурации подключения.
 */
interface ClientFactory
{
    /**
     * Создать настроенный экземпляр S3 клиента
     *
     * @param S3ClientConfig $config
     * @return S3Client
     * @throws StorageClientConfigurationException в случае неверных данных подключения
     */
    public function createClient(S3ClientConfig $config): S3Client;
}
