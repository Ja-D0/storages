<?php

namespace JaD0\Storages\Interfaces;

/**
 * Базовый контракт объектного хранилища с полным циклом чтения и записи объектов.
 */
interface Storage extends WritableStorage, ReadableStorage
{
}
