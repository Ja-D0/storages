<?php

namespace JaD0\Storages\Interfaces;

use Exception;

/**
 * Контракт обработчика, который получает результат операций кластера и поддерживает консистентность узлов.
 */
interface ClusterConsistencyManager
{
    /**
     * Обработать сценарий загрузки
     *
     * @param string $path
     * @param array $successNodesKeys
     * @param array $failNodesKeys
     * @return void
     * @throws Exception
     */
    public function handlePush(string $path, array $successNodesKeys, array $failNodesKeys): void;

    /**
     * Обработать сценарий копирования
     *
     * @param string $srcPath
     * @param string $destPath
     * @param array $successNodesKeys
     * @param array $failNodesKeys
     * @return void
     * @throws Exception
     */
    public function handleCopy(string $srcPath, string $destPath, array $successNodesKeys, array $failNodesKeys): void;

    /**
     * Обработать сценарий удаления
     *
     * @param string $path
     * @param array $failNodesKeys
     * @return void
     * @throws Exception
     */
    public function handleRemove(string $path, array $failNodesKeys): void;
}
