<?php

namespace Tests\Stubs;

use Prwnr\Streamer\Contracts\Event;

class FooBarStreamerEventStub implements Event
{
    public function name(): string
    {
        return 'foo.bar';
    }

    public function type(): string
    {
        return Event::TYPE_EVENT;
    }

    /**
     * @return array{foo: string}
     */
    public function payload(): array
    {
        return ['foo' => 'bar'];
    }
}
