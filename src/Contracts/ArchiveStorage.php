<?php

namespace Prwnr\Streamer\Contracts;

use Illuminate\Support\Collection;
use Prwnr\Streamer\EventDispatcher\Message;

interface ArchiveStorage
{
    /**
     * Stores message in storage.
     *
     * @param  Message  $message
     */
    public function create(Message $message): void;

    /**
     * Finds message in storage by stream name and ID.
     *
     * @param  string  $event
     * @param  string  $id
     * @return Message|null
     */
    public function find(string $event, string $id): ?Message;

    /**
     * Finds messages in storage by stream name.
     *
     * @param  string  $event
     * @return Collection&Message[]
     */
    public function findMany(string $event): Collection;

    /**
     * Returns all archived messages.
     * Do consider that the amount of messages may be huge, and it may impact performance.
     *
     * @return Collection&Message[]
     */
    public function all(): Collection;

    /**
     * Deletes message from the storage.
     *
     * @param  string  $event
     * @param  string|null  $id  without ID being passed, all messages of a given event should be deleted.
     * @return int count of deleted messages
     */
    public function delete(string $event, string $id = null): int;
}