<?php

namespace JaD0\Storages\Interfaces;

/**
 * Контракт фабрики, скрывающей создание конкретного хранилища за строковым идентификатором.
 */
interface StorageFactory
{
    /**
     * Создает экземпляр хранилища на основе идентификатора (бакета, контейнера или папки).
     *
     * @param string $identifier Имя бакета (S3), контейнера (Swift) или поддиректории (Local)
     * @return Storage
     */
    public function createStorage(string $identifier): Storage;
}
