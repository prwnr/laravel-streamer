<?php

namespace Tests;

use Prwnr\Streamer\Contracts\Event;
use Prwnr\Streamer\Contracts\StreamableMessage;
use Prwnr\Streamer\Facades\Streamer;
use Prwnr\Streamer\StreamerProvider;
use Tests\Stubs\MessageStub;
use Tests\Stubs\StreamerEventStub;

class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            StreamerProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Streamer' => Streamer::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.redis.options', [
            'profile' => '5.0',
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
