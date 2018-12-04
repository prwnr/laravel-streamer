<?php

namespace Tests;

use Prwnr\Streamer\Contracts\Event;
use Prwnr\Streamer\Contracts\StreamableMessage;
use Tests\Stubs\MessageStub;
use Tests\Stubs\StreamerEventStub;

class TestCase extends \Orchestra\Testbench\TestCase
{

    protected function getPackageProviders($app)
    {
        return [
            \Prwnr\Streamer\StreamerProvider::class
        ];
    }

    protected function getPackageAliases($app)
    {
        return [
            'Streamer' => \Prwnr\Streamer\Facades\Streamer::class
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.redis.options', [
            'profile' => '5.0'
        ]);
    }

    protected function makeMessage(): StreamableMessage
    {
        return new MessageStub();
    }

    protected function makeEvent(): Event
    {
        return new StreamerEventStub();
    }
}