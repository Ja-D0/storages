<?php

namespace JaD0\Storages\Adapters\S3;

use Aws\S3\S3Client;
use JaD0\Storages\Exceptions\StorageClientConfigurationException;

/**
 * Фабрика S3 клиента из DTO конфигурации подключения.
 */
class S3ClientFactory implements ClientFactory
{
    /**
     * @inheritDoc
     */
    public function createClient(S3ClientConfig $config): S3Client
    {
        if (empty($config->accessKey) || empty($config->secretKey)) {
            throw new StorageClientConfigurationException("S3 credentials (access/secret) не могут быть пустыми.");
        }

        $options = [
            "version" => "latest",
            "region" => $config->region,
            "endpoint" => $config->endpoint,
            "use_path_style_endpoint" => $config->usePathStyleEndpoint,
            "credentials" => [
                "key" => $config->accessKey,
                "secret" => $config->secretKey,
            ],
        ];

        if ($config->requestOptions !== null) {
            $options = array_replace($options, $config->requestOptions->toArray());
        }

        return new S3Client($options);
    }
}
