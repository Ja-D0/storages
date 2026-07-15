<?php

namespace JaD0\Storages\Exceptions;

use Exception;
use JaD0\Storages\Interfaces\Storage;
use Throwable;

/**
 * Исключение для ошибок операций с объектами хранилища.
 * Хранит признак того, что ошибка связана с недоступностью хранилища.
 *
 * @see Storage
 */
class StorageException extends Exception
{
    /**
     * Признак ошибки, связанной с недоступностью хранилища.
     *
     * @var bool
     */
    protected $notAvailable;

    /**
     * Конструктор.
     *
     * @param string $message
     * @param bool $notAvailable
     * @param Throwable|null $previous
     */
    public function __construct(
        string $message = "",
        bool $notAvailable = false,
        ?Throwable $previous = null
    )
    {
        $this->notAvailable = $notAvailable;
        parent::__construct($message, 0, $previous);
    }

    /**
     * Возвращает признак ошибки недоступности хранилища.
     *
     * @return bool
     */
    public function isStorageNotAvailable(): bool
    {
        return $this->notAvailable;
    }
}
