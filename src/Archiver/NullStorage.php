<?php

namespace Prwnr\Streamer\Archiver;

use Illuminate\Support\Collection;
use Prwnr\Streamer\Contracts\ArchiveStorage;
use Prwnr\Streamer\EventDispatcher\Message;

class NullStorage implements ArchiveStorage
{
    /**
     * @inheritDoc
     */
    public function create(Message $message): void
    {
        //
    }

    /**
     * @inheritDoc
     */
    public function find(string $event, string $id): ?Message
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function findMany(string $event): Collection
    {
        return collect();
    }

    /**
     * @inheritDoc
     */
    public function all(): Collection
    {
        return collect();
    }

    /**
     * @inheritDoc
     */
    public function delete(string $event, string $id = null): int
    {
        return 0;
    }
}