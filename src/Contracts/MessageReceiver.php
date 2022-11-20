<?php

declare(strict_types=1);

namespace Prwnr\Streamer\Contracts;

use Prwnr\Streamer\EventDispatcher\ReceivedMessage;

interface MessageReceiver
{
    /**
     * Handles event as ReceivedMessage.
     */
    public function handle(ReceivedMessage $message): void;
}
