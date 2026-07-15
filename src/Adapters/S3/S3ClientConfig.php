<?php

namespace JaD0\Storages\Adapters\S3;

use JaD0\Storages\Config\RequestOptions;

/**
 * DTO конфигурации подключения к S3-совместимому хранилищу.
 */
class S3ClientConfig
{
    /**
     * Endpoint S3-совместимого API.
     *
     * @var string
     */
    public $endpoint;

    /**
     * Access key для подключения.
     *
     * @var string
     */
    public $accessKey;

    /**
     * Secret key для подключения.
     *
     * @var string
     */
    public $secretKey;

    /**
     * Регион S3-клиента.
     *
     * @var string
     */
    public $region;

    /**
     * Использовать path-style endpoint для совместимости с S3-провайдерами.
     *
     * @var bool
     */
    public $usePathStyleEndpoint;

    /**
     * Сетевые настройки запросов SDK-клиента.
     *
     * @var RequestOptions|null
     */
    public $requestOptions;

    /**
     * Конструктор.
     *
     * @param string $endpoint
     * @param string $accessKey
     * @param string $secretKey
     * @param string $region
     * @param bool $usePathStyleEndpoint
     * @param RequestOptions|null $requestOptions
     */
    public function __construct(
        string $endpoint,
        string $accessKey,
        string $secretKey,
        string $region,
        bool $usePathStyleEndpoint = true,
        ?RequestOptions $requestOptions = null
    )
    {
        $this->endpoint = $endpoint;
        $this->accessKey = $accessKey;
        $this->secretKey = $secretKey;
        $this->region = $region;
        $this->usePathStyleEndpoint = $usePathStyleEndpoint;
        $this->requestOptions = $requestOptions;
    }
}
