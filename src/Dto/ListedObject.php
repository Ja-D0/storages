<?php

namespace JaD0\Storages\Dto;

/**
 * Метаданные объекта, найденного при листинге хранилища.
 */
class ListedObject
{
    /**
     * Путь объекта внутри хранилища.
     *
     * @var string
     */
    public $path;

    /**
     * Имя объекта без родительских префиксов.
     *
     * @var string
     */
    public $basename;

    /**
     * Размер объекта в байтах, если провайдер возвращает это значение.
     *
     * @var int|null
     */
    public $size;

    /**
     * Дата последнего изменения в формате, возвращаемом провайдером.
     *
     * @var mixed|null
     */
    public $lastModified;

    /**
     * Конструктор.
     *
     * @param string $path
     * @param string $basename
     * @param int|null $size
     * @param mixed|null $lastModified
     */
    public function __construct(string $path, string $basename, ?int $size = null, $lastModified = null)
    {
        $this->path = $path;
        $this->basename = $basename;
        $this->size = $size;
        $this->lastModified = $lastModified;
    }
}
