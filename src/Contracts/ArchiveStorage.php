<?php

namespace Prwnr\Streamer\Contracts;

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
     * Deletes message from the storage.
     */
    public function delete(string $event, string $id): void;
}