<?php

namespace Prwnr\Streamer\Contracts;

use Prwnr\Streamer\EventDispatcher\ReceivedMessage;

/**
 * Interface MessageReceiver
 */
interface MessageReceiver
{
    /**
     * Handles event as ReceivedMessage
     */
    public function handle(ReceivedMessage $message): void;
}
