<?php

declare(strict_types=1);

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

    public function payload(): array
    {
        return ['foo' => 'bar'];
    }
}
