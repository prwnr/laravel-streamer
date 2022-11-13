<?php

namespace Tests;

use Prwnr\Streamer\Archiver\StorageManager;
use Tests\Stubs\MemoryArchiveStorage;

trait WithMemoryManager
{
    /**
     * @var null|StorageManager
     */
    protected $manager;

    public function setUpMemoryManager(): void
    {
        /** @var StorageManager $manager */
        $this->manager = app(StorageManager::class);
        $this->manager->extend('memory', static function () {
            return new MemoryArchiveStorage();
        });

        config()->set('streamer.archive.storage_driver', 'memory');
    }
}
