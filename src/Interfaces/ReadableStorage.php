<?php

namespace JaD0\Storages\Interfaces;

use JaD0\Storages\Exceptions\StorageException;
use JaD0\Storages\Exceptions\StorageObjectNotFoundException;
use JaD0\Storages\Exceptions\StorageUnavailableException;
use Psr\Http\Message\StreamInterface;

/**
 * Контракт хранилища, из которого можно проверять наличие объектов и читать их как поток.
 */
interface ReadableStorage
{
    /**
     * Проверить существует ли объект в хранилище.
     * Возвращает false только тогда, когда хранилище достоверно подтвердило отсутствие объекта.
     *
     * @param string $path
     * @return bool
     * @throws StorageException в случае технической ошибки хранилища
     */
    public function exists(string $path): bool;

    /**
     * Прочитать объект и записать его в поток
     *
     * @param string $path
     * @return StreamInterface
     * @throws StorageObjectNotFoundException если объект достоверно отсутствует
     * @throws StorageUnavailableException если хранилище недоступно
     * @throws StorageException в случае другой технической ошибки во время чтения файла
     */
    public function readAsStream(string $path): StreamInterface;
}
