<?php

declare(strict_types=1);

namespace Prwnr\Streamer\Contracts;

use Prwnr\Streamer\EventDispatcher\Message;
use Prwnr\Streamer\EventDispatcher\ReceivedMessage;
use Prwnr\Streamer\Exceptions\ArchivizationFailedException;

interface Archiver
{
    /**
     * Archives message by deleting it from the stream and storing in database.
     *
     * @throws ArchivizationFailedException
     */
    public function archive(ReceivedMessage $message): void;

    /**
     * Restores message back to the stream and removes it from storage.
     *
     * @return string ID of new stream message restored from the given one
     */
    public function restore(Message $message): string;
}
