<?php

use Illuminate\Support\Collection;
use Prwnr\Streamer\Archiver\StorageManager;
use Prwnr\Streamer\Contracts\ArchiveStorage;
use Prwnr\Streamer\EventDispatcher\Message;

/**
 * Creation of Archive storage driver
 */
// The driver class should implement ArchiveStorage contract and all its methods like in this example:
class MemoryStorageExample implements ArchiveStorage
{
    private array $items = [];

    /**
     * Creation of the message in storage (database or else)
     */
    public function create(Message $message): void
    {
        $this->items[$message->getEventName()][$message->getId()] = $message;
    }

    /**
     * Finding message in storage by event and ID
     * Both values are required, as different events may have same IDs
     */
    public function find(string $event, string $id): ?Message
    {
        return $this->items[$event][$id] ?? null;
    }

    /**
     * Finding many messages in storage by event name.
     * Keep in mind that messages on different events may have same IDs,
     * so they shouldn't be used as keys in collection.
     */
    public function findMany(string $event): Collection
    {
        return collect($this->items[$event] ?? []);
    }

    /**
     * Finding all messages in storage.
     * This may be really heavy call and some chunks should be considered.
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
     * Deletes message from storage.
     * Either single one (by event and ID) or multiple ones (by event).
     * Should return number of messages deleted, 0 for none.
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

// Then, the new driver needs to be added to StorageManager with some unique name
$manager = $this->app->make(StorageManager::class);
$manager->extend('memory_example', static function () {
    return new MemoryStorageExample();
});

// The last step is to define your custom driver as default for the Archiver. You will find the field to change
// in streamer.php config file. Use your custom name there as archive.storage_driver

// Defining your custom driver as default one for Archiver is important, because otherwise it will use the Null driver
// which will result in message being only purged, without being passed to any storage.

