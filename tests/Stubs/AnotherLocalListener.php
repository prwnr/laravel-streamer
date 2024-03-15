<?php

declare(strict_types=1);

namespace Tests\Stubs;

use Prwnr\Streamer\Contracts\MessageReceiver;
use Prwnr\Streamer\EventDispatcher\ReceivedMessage;

class AnotherLocalListener implements MessageReceiver
{
    public function handle(ReceivedMessage $message): void
    {
        // TODO: Implement handle() method.
    }
}
