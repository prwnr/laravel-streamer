<?php

namespace Tests\Stubs;

use Prwnr\Streamer\Contracts\MessageReceiver;
use Prwnr\Streamer\EventDispatcher\ReceivedMessage;
use RuntimeException;

class ExceptionalListener implements MessageReceiver
{
    /**
     * @param  ReceivedMessage  $message
     */
    public function handle(ReceivedMessage $message): void
    {
        throw new RuntimeException('Listener failed.');
    }
}
