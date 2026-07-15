<?php

namespace JaD0\Storages\Availability;

use JaD0\Storages\Interfaces\StorageAvailabilityManager;

/**
 * Реализация StorageAvailabilityManager для окружений, где все узлы считаются доступными.
 */
class AlwaysAvailableStorageAvailabilityManager implements StorageAvailabilityManager
{
    /**
     * @inheritDoc
     */
    public function isAvailable(string $storageKey): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function markAvailable(string $storageKey): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function markNotAvailable(string $storageKey, int $ttl = 60): bool
    {
        return true;
    }
}
