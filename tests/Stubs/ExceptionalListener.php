<?php

declare(strict_types=1);

namespace Tests\Stubs;

use Prwnr\Streamer\Contracts\MessageReceiver;
use Prwnr\Streamer\EventDispatcher\ReceivedMessage;
use RuntimeException;

class ExceptionalListener implements MessageReceiver
{
    public function handle(ReceivedMessage $message): void
    {
        throw new RuntimeException('Listener failed.');
    }
}
