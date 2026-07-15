<?php

namespace JaD0\Storages\Config;

/**
 * DTO сетевых настроек запроса для SDK-клиентов хранилищ.
 */
class RequestOptions
{
    /**
     * Общее время запроса (подключение + ожидание ответа), в секундах.
     *
     * @var float|null
     */
    public $timeout;

    /**
     * Таймаут только на установку TCP-соединения.
     *
     * @var float|null
     */
    public $connectTimeout;

    /**
     * Конструктор.
     *
     * @param float|null $timeout
     * @param float|null $connectTimeout
     */
    public function __construct(?float $timeout = null, ?float $connectTimeout = null)
    {
        $this->timeout = $timeout;
        $this->connectTimeout = $connectTimeout;
    }

    /**
     * Преобразует DTO в массив опций для SDK.
     *
     * @return array
     */
    public function toArray(): array
    {
        $options = [];

        if ($this->timeout !== null) {
            $options["timeout"] = $this->timeout;
        }

        if ($this->connectTimeout !== null) {
            $options["connect_timeout"] = $this->connectTimeout;
        }

        return $options;
    }
}
