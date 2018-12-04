<?php

namespace Tests\Stubs;

use Prwnr\Streamer\Contracts\Event;

class StreamerEventStub implements Event
{

    public function name(): string
    {
        return 'foo.bar';
    }

    public function type(): string
    {
        return Event::TYPE_EVENT;
    }

    public function payload(): array
    {
        return ['foo' => 'bar'];
    }
}