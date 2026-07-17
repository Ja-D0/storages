<?php

namespace JaD0\Storages\Interfaces;

use JaD0\Storages\Exceptions\StorageException;
use JaD0\Storages\Exceptions\StorageObjectNotFoundException;
use Psr\Http\Message\StreamInterface;

/**
 * Контракт хранилища, которое поддерживает запись, копирование и удаление объектов.
 */
interface WritableStorage
{
    /**
     * Загрузить объект в хранилище
     *
     * @param string $localPath
     * @param string $storagePath
     * @return void
     * @throws StorageException в случае ошибки при загрузке файла
     */
    public function push(string $localPath, string $storagePath): void;

    /**
     * Создает копию существующего объекта по новому пути.
     *
     * @param string $srcPath
     * @param string $destPath
     * @return void
     * @throws StorageObjectNotFoundException если исходный объект достоверно отсутствует
     * @throws StorageException в случае ошибки при копировании
     */
    public function copy(string $srcPath, string $destPath): void;

    /**
     * Удалить объект из хранилища.
     *
     * Отсутствующий объект считается успешно удалённым. Реализация не должна
     * считать успешным удаление из отсутствующего или недоступного хранилища.
     *
     * @param string $path
     * @return void
     * @throws StorageException в случае ошибки при удалении файла
     */
    public function remove(string $path): void;

    /**
     * Записать поток в объект хранилища
     *
     * @param StreamInterface $stream
     * @param string $storagePath
     * @return void
     * @throws StorageException в случае ошибки во время записи
     */
    public function writeStream(StreamInterface $stream, string $storagePath): void;
}
