<?php

namespace Tests\Stubs;

use Prwnr\Streamer\Channel;

class FooBarChannel extends Channel {
    public function getGroup(): string
    {
        return 'foo';
    }

    public function getConsumer(): string
    {
        return 'bar';
    }

}