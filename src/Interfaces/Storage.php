<?php

namespace JaD0\Storages\Interfaces;

/**
 * Базовый контракт объектного хранилища с полным циклом чтения, записи и листинга объектов.
 */
interface Storage extends WritableStorage, ReadableListableStorage
{
}
