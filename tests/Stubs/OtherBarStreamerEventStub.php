<?php

declare(strict_types=1);

namespace Tests\Stubs;

use Prwnr\Streamer\Contracts\Event;

class OtherBarStreamerEventStub implements Event
{
    public function name(): string
    {
        return 'other.bar';
    }

    public function type(): string
    {
        return Event::TYPE_EVENT;
    }

    public function payload(): array
    {
        return ['other' => 'bar'];
    }
}
