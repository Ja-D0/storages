<?php

namespace JaD0\Storages\Adapters\Swift;

use JaD0\Storages\Exceptions\StorageClientConfigurationException;
use OpenStack\OpenStack;

/**
 * Контракт фабрики OpenStack подключения из конфигурации Swift-провайдера.
 *
 * @see OpenStack
 */
interface ClientFactory
{
    /**
     * Создать настроенное подключение OpenStack.
     *
     * @param SwiftClientConfig $config
     * @return OpenStack
     * @throws StorageClientConfigurationException в случае неверных данных подключения
     */
    public function createClient(SwiftClientConfig $config): OpenStack;
}
