<?php

namespace Tests\Stubs;

use Prwnr\Streamer\EventDispatcher\ReceivedMessage;

class LocalEventStub
{
    public function __construct(ReceivedMessage $message)
    {
    }
}
