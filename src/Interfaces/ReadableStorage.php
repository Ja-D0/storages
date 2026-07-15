<?php

namespace JaD0\Storages\Interfaces;

use JaD0\Storages\Exceptions\StorageException;
use Psr\Http\Message\StreamInterface;

/**
 * Контракт хранилища, из которого можно проверять наличие объектов и читать их как поток.
 */
interface ReadableStorage
{
    /**
     * Проверить существует ли объект в хранилище
     *
     * @param string $path
     * @return bool
     * @throws StorageException
     */
    public function exists(string $path): bool;

    /**
     * Прочитать объект и записать его в поток
     *
     * @param string $path
     * @return StreamInterface
     * @throws StorageException в случае ошибки во время чтения файла
     */
    public function readAsStream(string $path): StreamInterface;
}
