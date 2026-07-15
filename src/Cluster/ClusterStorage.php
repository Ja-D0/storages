<?php

namespace JaD0\Storages\Cluster;

use Exception;
use JaD0\Storages\Exceptions\StorageConfigurationException;
use JaD0\Storages\Exceptions\StorageException;
use JaD0\Storages\Interfaces\Cluster;
use JaD0\Storages\Interfaces\ClusterConsistencyManager;
use JaD0\Storages\Interfaces\StorageAvailabilityManager;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;

/**
 * Составное хранилище, которое выполняет операции над набором Storage-узлов как над единым хранилищем.
 */
class ClusterStorage implements Cluster
{
    /**
     * Узлы хранилища, индексированные ключами кластера.
     *
     * @var array
     */
    protected $storages;

    /**
     * @var ClusterConsistencyManager
     */
    protected $consistencyManager;

    /**
     * @var StorageAvailabilityManager
     */
    protected $storageAvailabilityManager;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Конструктор.
     *
     * @param array $storages
     * @param ClusterConsistencyManager $consistencyManager
     * @param StorageAvailabilityManager $storageAvailabilityManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        array $storages,
        ClusterConsistencyManager $consistencyManager,
        StorageAvailabilityManager $storageAvailabilityManager,
        LoggerInterface $logger
    )
    {
        $this->storages = $storages;
        $this->consistencyManager = $consistencyManager;
        $this->storageAvailabilityManager = $storageAvailabilityManager;
        $this->logger = $logger;

        if (empty($storages)) {
            throw new StorageConfigurationException("ClusterStorage должно иметь хотя бы одно базовое хранилище");
        }
    }

    /**
     * Проверить существует ли объект в кластере хранилищ. Если известно, что хранилище недоступно, оно пропускается.
     * Если хранилище доступно на момент проверки доступности менеджером, но будет недоступно во время проверки объекта,
     * то оно будет помечено как недоступное в будущем.
     *
     * @return bool true, если объект присутствует хотя бы в одном доступном хранилище,
     * false, если все проверенные доступные хранилища подтвердили отсутствие объекта
     * @throws StorageException если ни одно хранилище не было доступно
     */
    public function exists(string $path): bool
    {
        $successfulFalse = 0;
        $failedNodes = [];
        $availableNodes = $notAvailableNodes = [];

        foreach ($this->storages as $nodeKey => $storage) {
            if (!$this->storageAvailabilityManager->isAvailable($nodeKey)) {
                continue;
            }

            try {
                if ($storage->exists($path)) {
                    $availableNodes[] = $nodeKey;
                    $this->reportNodesAvailabilityStates($availableNodes, $notAvailableNodes);

                    return true;
                }

                $availableNodes[] = $nodeKey;
                $successfulFalse++;
            } catch (StorageException $storageException) {
                $failedNodes[] = $nodeKey;

                $this->logger->error(
                    "Ошибка при проверке наличия объекта '$path' в узле $nodeKey: "
                    . $storageException->getMessage(),
                    ["category" => "storage.ClusterStorage", "exception" => $storageException]
                );

                if ($storageException->isStorageNotAvailable()) {
                    $notAvailableNodes[] = $nodeKey;
                } else {
                    $availableNodes[] = $nodeKey;
                }
            }
        }

        $this->reportNodesAvailabilityStates($availableNodes, $notAvailableNodes);

        if ($successfulFalse > 0 && empty($failedNodes)) {
            return false;
        }

        throw new StorageException("Не удалось проверить наличие объекта '$path'");
    }

    /**
     * Создает копию существующего объекта по новому пути в каждом хранилище кластера.
     * Если известно, что хранилище недоступно, оно пропускается.
     * Если хранилище доступно на момент проверки доступности менеджером, но будет недоступно во время копирования объекта,
     * то оно будет помечено как недоступное в будущем. Если хотя бы одно хранилище успешно скопировало объект,
     * будет создана фоновая задача на синхронизацию этого объекта во всех хранилищах кластера
     *
     * @param string $srcPath
     * @param string $destPath
     * @return void
     * @throws StorageException, если ни одно хранилище не скопировало объект
     * @throws \Throwable
     */
    public function copy(string $srcPath, string $destPath): void
    {
        $successNodes = $failNodes = $availableNodes = $notAvailableNodes = [];

        foreach ($this->storages as $nodeKey => $storage) {
            if (!$this->storageAvailabilityManager->isAvailable($nodeKey)) {
                $failNodes[] = $nodeKey;
                continue;
            }

            try {
                $storage->copy($srcPath, $destPath);
                $successNodes[] = $nodeKey;
                $availableNodes[] = $nodeKey;
            } catch (StorageException $storageException) {
                $failNodes[] = $nodeKey;

                if ($storageException->isStorageNotAvailable()) {
                    $notAvailableNodes[] = $nodeKey;
                } else {
                    $availableNodes[] = $nodeKey;
                }

                $this->logger->warning(
                    "Ошибка при копировании объекта '$srcPath' -> '$destPath' в узле $nodeKey: "
                    . $storageException->getMessage(),
                    ["category" => "storage.ClusterStorage", "exception" => $storageException]
                );
            }
        }

        $this->reportNodesAvailabilityStates($availableNodes, $notAvailableNodes);

        if (empty($successNodes)) {
            throw new StorageException("Ошибка копирования объектов '$srcPath' -> '$destPath'");
        }

        if (!empty($failNodes)) {
            $this->logger->warning(
                "Не удалось скопировать объекты '$srcPath' -> '$destPath' в следующих узлах: "
                . implode(", ", $failNodes),
                ["category" => "storage.ClusterStorage"]
            );
        }

        try {
            $this->consistencyManager->handleCopy($srcPath, $destPath, $successNodes, $failNodes);
        } catch (Exception $e) {
            $storageException = new StorageException(
                "Критическая ошибка менеджера синхронизации для операции копирования объектов '$srcPath' -> '$destPath'",
                false,
                $e
            );
            $this->logger->error(
                "Критическая ошибка менеджера синхронизации для операции копирования объектов '$srcPath' -> '$destPath': " . $e->getMessage(),
                ["category" => "storage.ClusterStorage", "exception" => $storageException]
            );
        }
    }

    /**
     * Записывает локальный файл во все хранилища кластера.
     * Если известно, что хранилище недоступно, оно пропускается.
     * Если хранилище доступно на момент проверки доступности менеджером, но будет недоступно во время записи объекта,
     * то оно будет помечено как недоступное в будущем. Если хотя бы одно хранилище успешно приняло файл,
     * будет создана фоновая задача на синхронизацию этого объекта во всех хранилищах кластера.
     *
     * @param string $localPath
     * @param string $storagePath
     * @return void
     * @throws StorageException, если ни одно хранилище не сохранило файл
     * @throws \Throwable
     */
    public function push(string $localPath, string $storagePath): void
    {
        $successNodes = $failNodes = $availableNodes = $notAvailableNodes = [];

        foreach ($this->storages as $nodeKey => $storage) {
            if (!$this->storageAvailabilityManager->isAvailable($nodeKey)) {
                $failNodes[] = $nodeKey;
                continue;
            }

            try {
                $storage->push($localPath, $storagePath);
                $successNodes[] = $nodeKey;
                $availableNodes[] = $nodeKey;
            } catch (StorageException $storageException) {
                $failNodes[] = $nodeKey;

                if ($storageException->isStorageNotAvailable()) {
                    $notAvailableNodes[] = $nodeKey;
                } else {
                    $availableNodes[] = $nodeKey;
                }

                $this->logger->warning(
                    "Ошибка при записи объекта '$localPath' -> '$storagePath' в узле '$nodeKey': "
                    . $storageException->getMessage(),
                    ["category" => "storage.ClusterStorage", "exception" => $storageException]
                );
            }
        }

        $this->reportNodesAvailabilityStates($availableNodes, $notAvailableNodes);

        if (empty($successNodes)) {
            throw new StorageException("Ошибка при записи объекта '$localPath' -> '$storagePath'");
        }

        if (!empty($failNodes)) {
            $this->logger->warning(
                "Не удалось записать объект '$localPath' в следующие узлы: "
                . implode(", ", $failNodes),
                ["category" => "storage.ClusterStorage"]
            );
        }

        try {
            $this->consistencyManager->handlePush($storagePath, $successNodes, $failNodes);
        } catch (Exception $e) {
            $storageException = new StorageException(
                "Критическая ошибка менеджера синхронизации для операции записи объекта '$localPath' -> '$storagePath'",
                false,
                $e
            );
            $this->logger->error(
                "Критическая ошибка менеджера синхронизации для операции записи объекта '$localPath' -> '$storagePath': "
                . $e->getMessage(),
                ["category" => "storage.ClusterStorage", "exception" => $storageException]
            );
        }
    }

    /**
     * Удаляет объект изо всех хранилищ кластера.
     * Если известно, что хранилище недоступно, оно пропускается.
     * Если хранилище доступно на момент проверки доступности менеджером, но будет недоступно во время удаления объекта,
     * то оно будет помечено как недоступное в будущем. Если хотя бы одно хранилище успешно удалило файл,
     * остальные (включая недоступные) будут обработаны менеджером синхронизации для обеспечения консистентности в фоне.
     *
     * @param string $path
     * @return void
     * @throws StorageException, если ни одно хранилище не выполнило операцию удаления
     * @throws \Throwable
     *
     */
    public function remove(string $path): void
    {
        $successNodes = $failNodes = $availableNodes = $notAvailableNodes = [];

        foreach ($this->storages as $nodeKey => $storage) {
            if (!$this->storageAvailabilityManager->isAvailable($nodeKey)) {
                $failNodes[] = $nodeKey;
                continue;
            }

            try {
                $storage->remove($path);
                $successNodes[] = $nodeKey;
                $availableNodes[] = $nodeKey;
            } catch (StorageException $storageException) {
                $failNodes[] = $nodeKey;

                if ($storageException->isStorageNotAvailable()) {
                    $notAvailableNodes[] = $nodeKey;
                } else {
                    $availableNodes[] = $nodeKey;
                }

                $this->logger->error(
                    "Ошибка при удалении '$path' в узле $nodeKey: "
                    . $storageException->getMessage(),
                    ["category" => "storage.ClusterStorage", "exception" => $storageException]
                );
            }
        }

        $this->reportNodesAvailabilityStates($availableNodes, $notAvailableNodes);

        if (empty($successNodes)) {
            throw new StorageException("Не удалось удалить объекты '$path'");
        }

        if (!empty($failNodes)) {
            $this->logger->warning(
                "Не удалось удалить '$path' в следующих узлах: " . implode(", ", $failNodes),
                ["category" => "storage.ClusterStorage"]
            );
        }

        try {
            $this->consistencyManager->handleRemove($path, $failNodes);
        } catch (Exception $e) {
            $storageException = new StorageException(
                "Критическая ошибка менеджера синхронизации для операции удаления объекта '$path'",
                false,
                $e
            );
            $this->logger->error(
                "Критическая ошибка менеджера синхронизации для операции удаления объектов '$path': "
                . $e->getMessage(),
                ["category" => "storage.ClusterStorage", "exception" => $storageException]
            );
        }
    }

    /**
     * Читает объект из кластера и возвращает поток данных.
     * Перебирает хранилища до первого успешного получения потока. Если известно, что хранилище недоступно, оно пропускается.
     * Если хранилище доступно на момент проверки доступности менеджером, но будет недоступно во время открытия потока,
     * то оно будет помечено как недоступное в будущем.
     *
     * @param string $path
     * @return StreamInterface
     * @throws StorageException, если объект не найден или ни одно хранилище не было доступно для чтения
     */
    public function readAsStream(string $path): StreamInterface
    {
        $availableNodes = $notAvailableNodes = [];

        foreach ($this->storages as $nodeKey => $storage) {
            if (!$this->storageAvailabilityManager->isAvailable($nodeKey)) {
                continue;
            }

            try {
                $stream = $storage->readAsStream($path);
                $availableNodes[] = $nodeKey;
                $this->reportNodesAvailabilityStates($availableNodes, $notAvailableNodes);

                return $stream;
            } catch (StorageException $storageException) {
                if ($storageException->isStorageNotAvailable()) {
                    $notAvailableNodes[] = $nodeKey;
                } else {
                    $availableNodes[] = $nodeKey;
                }

                $this->logger->error(
                    "Ошибка при чтении файла '$path' из узла $nodeKey: "
                    . $storageException->getMessage(),
                    ["category" => "storage.ClusterStorage", "exception" => $storageException]
                );
            }
        }

        $this->reportNodesAvailabilityStates($availableNodes, $notAvailableNodes);

        throw new StorageException("Файл не найден: $path");
    }

    /**
     * Записывает поток данных во все хранилища кластера.
     * Если известно, что хранилище недоступно, оно пропускается.
     * Если хранилище доступно на момент проверки доступности менеджером, но будет недоступно во время записи потока,
     * то оно будет помечено как недоступное в будущем.
     * Если хотя бы одно хранилище успешно приняло поток, будет создана фоновая задача на синхронизацию этого
     * объекта во всех хранилищах.
     *
     * @param StreamInterface $stream
     * @param string $storagePath
     * @return void
     * @throws StorageException, если ни одно хранилище не сохранило поток
     * @throws \Throwable
     */
    public function writeStream(StreamInterface $stream, string $storagePath): void
    {
        $successNodes = $failNodes = $availableNodes = $notAvailableNodes = [];

        foreach ($this->storages as $nodeKey => $storage) {
            if (!$this->storageAvailabilityManager->isAvailable($nodeKey)) {
                $failNodes[] = $nodeKey;
                continue;
            }

            try {
                $storage->writeStream($stream, $storagePath);
                $availableNodes[] = $nodeKey;
                $successNodes[] = $nodeKey;
            } catch (StorageException $storageException) {
                $failNodes[] = $nodeKey;

                if ($storageException->isStorageNotAvailable()) {
                    $notAvailableNodes[] = $nodeKey;
                } else {
                    $availableNodes[] = $nodeKey;
                }

                $this->logger->error(
                    "Ошибка при записи потока в '$storagePath' в узле $nodeKey: "
                    . $storageException->getMessage(),
                    ["category" => "storage.ClusterStorage", "exception" => $storageException]
                );
            } finally {
                if ($stream->isSeekable()) {
                    $stream->rewind();
                }
            }
        }

        $this->reportNodesAvailabilityStates($availableNodes, $notAvailableNodes);

        if (empty($successNodes)) {
            throw new StorageException("Ошибка записи потока в '$storagePath'");
        }

        if (!empty($failNodes)) {
            $this->logger->warning(
                "Не удалось записать поток в '$storagePath' в следующие узлы: "
                . implode(", ", $failNodes),
                ["category" => "storage.ClusterStorage"]
            );
        }

        try {
            $this->consistencyManager->handlePush($storagePath, $successNodes, $failNodes);
        } catch (Exception $e) {
            $storageException = new StorageException(
                "Критическая ошибка менеджера синхронизации для операции записи потока",
                false,
                $e
            );
            $this->logger->error(
                "Критическая ошибка менеджера синхронизации для операции записи потока: " . $e->getMessage(),
                ["category" => "storage.ClusterStorage", "exception" => $storageException]
            );
        }
    }

    /**
     * Сообщить о состоянии доступности узлов менеджеру доступности узлов
     *
     * @param string[] $availableNodesKeys
     * @param string[] $notAvailableNodesKeys
     * @return void
     */
    protected function reportNodesAvailabilityStates(array $availableNodesKeys, array $notAvailableNodesKeys): void
    {
        foreach ($availableNodesKeys as $nodeKey) {
            if (!$this->storageAvailabilityManager->isAvailable($nodeKey)) {
                $this->storageAvailabilityManager->markAvailable($nodeKey);
            }
        }

        foreach ($notAvailableNodesKeys as $nodeKey) {
            $this->storageAvailabilityManager->markNotAvailable($nodeKey);
        }
    }
}
