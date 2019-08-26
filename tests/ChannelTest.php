<?php

namespace Tests;

use Illuminate\Foundation\Testing\Concerns\InteractsWithRedis;
use Prwnr\Streamer\EventDispatcher\ReceivedMessage;
use Tests\Stubs\FooBarChannel;

class ChannelTest extends TestCase
{
    use InteractsWithRedis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRedis();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->redis['predis']->connection()->flushall();
        $this->tearDownRedis();
    }

    public function testSendingSingleMes(): void
    {
        $channel = new FooBarChannel();
        $channel->send(['foo' => 'bar']);

        $channel->receive(function (ReceivedMessage $message) {
            $this->assertEquals(['foo' => 'bar'], $message->get());

            return false;
        });
    }

    public function testSendingMultipleMessages(): void
    {
        $channel = new FooBarChannel();
        $channel->send(['foo' => 'first bar']);
        $channel->send(['foo' => 'other bar']);

        $channel->receive(function (ReceivedMessage $message) {
            $expected = $message->get('foo') === 'first bar' || $message->get('foo') === 'other bar';
            $this->assertTrue($expected);

            return false;
        });
    }

    public function testReceivingEmptyMessage(): void
    {
        $channel = new FooBarChannel();
        $channel->setTimeout(1000);

        $channel->receive(function (ReceivedMessage $message) {
            $this->assertEmpty($message->getId());
            return false;
        });
    }
}