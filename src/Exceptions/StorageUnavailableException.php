<?php

namespace JaD0\Storages\Exceptions;

use Throwable;

/**
 * Операция не выполнена из-за недоступности хранилища.
 */
class StorageUnavailableException extends StorageException
{
    /**
     * Конструктор.
     *
     * @param string $message Описание ошибки
     * @param Throwable|null $previous Исходное исключение адаптера, если оно доступно
     */
    public function __construct(string $message = "Storage is unavailable", ?Throwable $previous = null)
    {
        parent::__construct($message, true, $previous);
    }
}
