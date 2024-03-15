<?php

namespace Prwnr\Streamer\Contracts;

use Prwnr\Streamer\EventDispatcher\ReceivedMessage;

interface MessageReceiver
{
    public function handle(ReceivedMessage $message): void;
}
