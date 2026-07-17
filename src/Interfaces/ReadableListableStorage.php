<?php

namespace JaD0\Storages\Interfaces;

/**
 * Контракт источника, который поддерживает чтение объектов и листинг по префиксу.
 */
interface ReadableListableStorage extends ReadableStorage, ListableStorage
{
}
