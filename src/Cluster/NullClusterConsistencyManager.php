<?php

namespace JaD0\Storages\Cluster;

use JaD0\Storages\Interfaces\ClusterConsistencyManager;

/**
 * Реализация ClusterConsistencyManager для кластеров, которым не нужна внешняя синхронизация.
 */
class NullClusterConsistencyManager implements ClusterConsistencyManager
{
    /**
     * @inheritDoc
     */
    public function handlePush(string $path, array $successNodesKeys, array $failNodesKeys): void
    {
    }

    /**
     * @inheritDoc
     */
    public function handleCopy(string $srcPath, string $destPath, array $successNodesKeys, array $failNodesKeys): void
    {
    }

    /**
     * @inheritDoc
     */
    public function handleRemove(string $path, array $failNodesKeys): void
    {
    }
}
