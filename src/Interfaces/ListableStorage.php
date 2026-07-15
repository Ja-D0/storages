<?php

namespace JaD0\Storages\Interfaces;

use JaD0\Storages\Dto\ListedObject;
use JaD0\Storages\Exceptions\StorageException;

/**
 * Контракт хранилища, которое умеет возвращать список объектов по префиксу.
 */
interface ListableStorage
{
    /**
     * Получить список объектов, путь которых начинается с указанного префикса.
     *
     * @param string $prefix
     * @return iterable|ListedObject[]
     * @throws StorageException
     */
    public function listObjects(string $prefix = ""): iterable;
}
