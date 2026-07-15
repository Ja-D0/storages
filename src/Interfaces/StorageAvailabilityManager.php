<?php

namespace JaD0\Storages\Interfaces;

/**
 * Контракт внешнего источника состояния доступности узлов хранилища.
 */
interface StorageAvailabilityManager
{
    /**
     * Проверить доступно ли хранилище
     *
     * @param string $storageKey
     * @return bool
     */
    public function isAvailable(string $storageKey): bool;

    /**
     * Пометить хранилище как доступное
     *
     * @param string $storageKey
     * @return bool
     */
    public function markAvailable(string $storageKey): bool;

    /**
     * Пометить хранилище как недоступное
     *
     * @param string $storageKey
     * @param int $ttl
     * @return bool
     */
    public function markNotAvailable(string $storageKey, int $ttl = 60): bool;
}
