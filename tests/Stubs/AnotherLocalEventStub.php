<?php

namespace Tests\Stubs;

use Prwnr\Streamer\EventDispatcher\ReceivedMessage;

class AnotherLocalEventStub
{
    public function __construct(ReceivedMessage $message)
    {
    }
}
