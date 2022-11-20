<?php

namespace Tests\Stubs;

use Prwnr\Streamer\Contracts\StreamableMessage;

class MessageStub implements StreamableMessage
{
    /**
     * @return array{foo: string}
     */
    public function getContent(): array
    {
        return ['foo' => 'bar'];
    }
}
