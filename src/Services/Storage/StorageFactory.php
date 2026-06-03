<?php

declare(strict_types=1);

namespace App\Services\Storage;

class StorageFactory
{
    public static function criar(): StorageInterface
    {
        $driver = getenv('STORAGE_DRIVER') ?: ($_ENV['STORAGE_DRIVER'] ?? 'local');

        return match($driver) {
            's3'    => new S3StorageService(),
            default => new LocalStorageService(),
        };
    }
}
