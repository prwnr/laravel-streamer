<?php

namespace Prwnr\Streamer\Contracts;

use Prwnr\Streamer\EventDispatcher\Message;
use Prwnr\Streamer\EventDispatcher\ReceivedMessage;
use Prwnr\Streamer\Exceptions\ArchivizationFailedException;

interface Archiver
{
    /**
     * Archives message by deleting it from the stream and storing in database.
     *
     * @param  ReceivedMessage  $message
     * @throws ArchivizationFailedException
     */
    public function archive(ReceivedMessage $message): void;

    /**
     * Restores message by fetching it from the database and emitting it back to the stream.
     *
     * @param  string  $event
     * @param  string  $id
     * @return ReceivedMessage|null
     */
    public function restore(string $event, string $id): ?Message;
}