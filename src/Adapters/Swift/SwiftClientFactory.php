<?php

namespace JaD0\Storages\Adapters\Swift;

use JaD0\Storages\Exceptions\StorageClientConfigurationException;
use OpenStack\OpenStack;

/**
 * Фабрика OpenStack подключения из DTO конфигурации Swift-провайдера.
 */
class SwiftClientFactory implements ClientFactory
{
    /**
     * @inheritDoc
     */
    public function createClient(SwiftClientConfig $config): OpenStack
    {
        if (empty($config->authUrl) || empty($config->password) || empty($config->userName) || empty($config->region)) {
            throw new StorageClientConfigurationException("OpenStack authUrl, password, userName и region не могут быть пустыми.");
        }

        return new OpenStack($this->prepareOptions($config));
    }

    /**
     * Подготавливает массив параметров для OpenStack
     *
     * @param SwiftClientConfig $config
     * @return array
     */
    protected function prepareOptions(SwiftClientConfig $config): array
    {
        $options = [
            "authUrl" => $config->authUrl,
            "region" => $config->region,

            "user" => [
                "name" => $config->userName,
                "domain" => [
                    "id" => $config->domainId,
                    "name" => $config->domainName
                ],
                "password" => $config->password
            ],
        ];

        if ($config->requestOptions !== null) {
            $options["requestOptions"] = $config->requestOptions->toArray();
        }

        return $options;
    }
}
