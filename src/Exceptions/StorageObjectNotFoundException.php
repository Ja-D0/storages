<?php

namespace JaD0\Storages\Exceptions;

use Throwable;

/**
 * Хранилище достоверно подтвердило отсутствие объекта.
 */
class StorageObjectNotFoundException extends StorageException
{
    /**
     * Путь отсутствующего объекта.
     *
     * @var string
     */
    protected $objectPath;

    /**
     * Конструктор.
     *
     * @param string $objectPath Путь отсутствующего объекта
     * @param Throwable|null $previous Исходное исключение адаптера, если оно доступно
     */
    public function __construct(string $objectPath, ?Throwable $previous = null)
    {
        $this->objectPath = $objectPath;

        parent::__construct("Storage object not found: $objectPath", false, $previous);
    }

    /**
     * Возвращает путь отсутствующего объекта.
     *
     * @return string
     */
    public function getObjectPath(): string
    {
        return $this->objectPath;
    }
}
