<?php

namespace JaD0\Storages\Support;

/**
 * Компонент определения MIME-типа локальных файлов для записи объектов в хранилище.
 */
final class MimeTypeResolver
{
    /**
     * Определяет MIME-тип локального файла на основе его содержимого.
     *
     * @param string $uri
     * @param string|null $defaultMimeType
     * @return string|null Возвращает MIME-тип или значение по умолчанию.
     */
    public static function resolve(string $uri, ?string $defaultMimeType = null): ?string
    {
        if (extension_loaded("fileinfo")) {
            $fInfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($fInfo, $uri);
            finfo_close($fInfo);

            return $mimeType ?: $defaultMimeType;
        }

        return $defaultMimeType;
    }
}
