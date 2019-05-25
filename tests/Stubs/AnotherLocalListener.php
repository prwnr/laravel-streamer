<?php

namespace Tests\Stubs;

use Prwnr\Streamer\Contracts\MessageReceiver;
use Prwnr\Streamer\EventDispatcher\ReceivedMessage;

class AnotherLocalListener implements MessageReceiver
{
    /**
     * @param  ReceivedMessage  $message
     */
    public function handle(ReceivedMessage $message): void
    {
        // TODO: Implement handle() method.
    }
}
