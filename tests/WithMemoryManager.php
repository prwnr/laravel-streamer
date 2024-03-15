<?php

declare(strict_types=1);

namespace Tests;

use Prwnr\Streamer\Archiver\StorageManager;
use Tests\Stubs\MemoryArchiveStorage;

trait WithMemoryManager
{
    protected ?StorageManager $manager;

    public function setUpMemoryManager(): void
    {
        $this->manager = app(StorageManager::class);
        $this->manager->extend('memory', static fn (): MemoryArchiveStorage => new MemoryArchiveStorage());

        config()->set('streamer.archive.storage_driver', 'memory');
    }
}
