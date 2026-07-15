<?php

namespace JaD0\Storages\Adapters\Swift;

use JaD0\Storages\Config\RequestOptions;

/**
 * DTO конфигурации подключения к OpenStack Swift/Object Store хранилищу.
 */
class SwiftClientConfig
{
    /**
     * URL OpenStack Identity API.
     *
     * @var string
     */
    public $authUrl;

    /**
     * Пароль пользователя OpenStack.
     *
     * @var string
     */
    public $password;

    /**
     * Имя пользователя OpenStack.
     *
     * @var string
     */
    public $userName;

    /**
     * Регион Object Store.
     *
     * @var string
     */
    public $region;

    /**
     * Идентификатор домена пользователя.
     *
     * @var string
     */
    public $domainId;

    /**
     * Название домена пользователя.
     *
     * @var string
     */
    public $domainName;

    /**
     * Сетевые настройки запросов SDK-клиента.
     *
     * @var RequestOptions|null
     */
    public $requestOptions;

    /**
     * Конструктор.
     *
     * @param string $authUrl
     * @param string $password
     * @param string $userName
     * @param string $region
     * @param string $domainId
     * @param string $domainName
     * @param RequestOptions|null $requestOptions
     */
    public function __construct(
        string $authUrl,
        string $password,
        string $userName,
        string $region,
        string $domainId = "default",
        string $domainName = "Default",
        ?RequestOptions $requestOptions = null
    )
    {
        $this->authUrl = $authUrl;
        $this->password = $password;
        $this->userName = $userName;
        $this->region = $region;
        $this->domainId = $domainId;
        $this->domainName = $domainName;
        $this->requestOptions = $requestOptions;
    }
}
