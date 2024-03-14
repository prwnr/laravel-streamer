<?php

namespace Tests;

use Prwnr\Streamer\Archiver\StorageManager;
use Tests\Stubs\MemoryArchiveStorage;

trait WithMemoryManager
{
    /**
     * @var null|StorageManager
     */
    protected ?StorageManager $manager;

    public function setUpMemoryManager(): void
    {
        $this->manager = app(StorageManager::class);
        $this->manager->extend('memory', static fn (): MemoryArchiveStorage => new MemoryArchiveStorage());

        config()->set('streamer.archive.storage_driver', 'memory');
    }
}
