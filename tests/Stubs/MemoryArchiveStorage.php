<?php

declare(strict_types=1);

namespace Tests\Stubs;

use Illuminate\Support\Collection;
use Prwnr\Streamer\Contracts\ArchiveStorage;
use Prwnr\Streamer\EventDispatcher\Message;

class MemoryArchiveStorage implements ArchiveStorage
{
    private array $items = [];

    /**
     * @inheritDoc
     */
    public function create(Message $message): void
    {
        $this->items[$message->getEventName()][$message->getId()] = $message;
    }

    /**
     * @inheritDoc
     */
    public function find(string $event, string $id): ?Message
    {
        return $this->items[$event][$id] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function findMany(string $event): Collection
    {
        return collect($this->items[$event] ?? []);
    }

    /**
     * @inheritDoc
     */
    public function all(): Collection
    {
        $collection = collect();
        foreach ($this->items as $messages) {
            $collection->push(...array_values($messages));
        }

        return $collection;
    }

    /**
     * @inheritDoc
     */
    public function delete(string $event, string $id = null): int
    {
        if ($id === null && isset($this->items[$event])) {
            $count = count($this->items[$event]);
            unset($this->items[$event]);
            return $count;
        }

        if (isset($this->items[$event][$id])) {
            unset($this->items[$event][$id]);
            return 1;
        }

        return 0;
    }
}
