<?php

namespace Tests;

use Illuminate\Foundation\Testing\Concerns\InteractsWithRedis;
use Illuminate\Support\Facades\Log;
use Prwnr\Streamer\EventDispatcher\ReceivedMessage;
use Prwnr\Streamer\EventDispatcher\Streamer;
use Prwnr\Streamer\Facades\Streamer as StreamerFacade;
use Prwnr\Streamer\History\EventHistory;
use Prwnr\Streamer\Stream;

class StreamerTest extends TestCase
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
        $this->redis['phpredis']->connection()->flushall();
        $this->tearDownRedis();
    }

    public function test_streamer_emits_event(): void
    {
        $streamer = new Streamer(new EventHistory());
        $event = $this->makeEvent();

        $id = $streamer->emit($event);
        $stream = new Stream('foo.bar');
        $actual = $stream->read();

        $this->assertNotNull($id);
        $this->assertNotEmpty($actual);
        $this->assertArrayHasKey('foo.bar', $actual);
        $this->assertArrayHasKey($id, $actual['foo.bar']);
    }

    public function test_streamer_facade_emits_event(): void
    {
        $event = $this->makeEvent();

        $id = StreamerFacade::emit($event);
        $stream = new Stream('foo.bar');
        $actual = $stream->read();

        $this->assertNotNull($id);
        $this->assertNotEmpty($actual);
        $this->assertArrayHasKey('foo.bar', $actual);
        $this->assertArrayHasKey($id, $actual['foo.bar']);
    }

    public function test_streamer_facade_emits_event_with_specified_id(): void
    {
        $event = $this->makeEvent();

        $id = StreamerFacade::emit($event, '123-0');
        $stream = new Stream('foo.bar');
        $actual = $stream->read();

        $this->assertNotNull($id);
        $this->assertEquals('123-0', $id);
        $this->assertNotEmpty($actual);
        $this->assertArrayHasKey('foo.bar', $actual);
        $this->assertArrayHasKey($id, $actual['foo.bar']);
    }

    public function test_streamer_listen_listens_to_and_handles_event(): void
    {
        $this->app['config']->set('streamer.stream_read_timeout', 0.01);
        $streamer = new Streamer(new EventHistory());
        $event = $this->makeEvent();

        $ids = [];
        $ids[] = $streamer->emit($event);
        $ids[] = $streamer->emit($event);
        $callback = function ($message, $streamer) use (&$ids) {
            $content = $message->getContent();
            $this->assertInstanceOf(ReceivedMessage::class, $message);
            $this->assertInstanceOf(Streamer::class, $streamer);
            $this->assertNotEmpty($content);
            $this->assertEquals(array_shift($ids), $message->getId());
            $this->assertEquals(['foo' => 'bar'], $content['data']);

            if (empty($ids)) {
                $streamer->cancel(); // break out of the listener loop
            }
        };

        $streamer->startFrom('0-0');
        $streamer->listen('foo.bar', $callback);
    }

    public function test_streamer_listen_listens_to_events_till_time_out(): void
    {
        $this->app['config']->set('streamer.listen_timeout', 2);
        $this->app['config']->set('streamer.stream_read_timeout', 0.01);
        $streamer = new Streamer(new EventHistory());
        $event = $this->makeEvent();

        $id = $streamer->emit($event);
        $callback = function ($message, $streamer) use ($id) {
            $content = $message->getContent();
            $this->assertInstanceOf(ReceivedMessage::class, $message);
            $this->assertInstanceOf(Streamer::class, $streamer);
            $this->assertNotEmpty($content);
            $this->assertEquals($id, $message->getId());
            $this->assertEquals(['foo' => 'bar'], $content['data']);
        };

        $streamer->startFrom('0-0');
        $streamer->listen('foo.bar', $callback);
    }

    public function test_streamer_listen_listens_to_and_handles_events_as_consumer(): void
    {
        (new Stream('foo.bar'))->createGroup('bar');
        $streamer = new Streamer(new EventHistory());
        $event = $this->makeEvent();

        $ids = [];
        $ids[] = $streamer->emit($event);
        $ids[] = $streamer->emit($event);
        $callback = function ($message, $streamer) use (&$ids) {
            $content = $message->getContent();
            $this->assertInstanceOf(ReceivedMessage::class, $message);
            $this->assertInstanceOf(Streamer::class, $streamer);
            $this->assertNotEmpty($content);
            $this->assertEquals(array_shift($ids), $message->getId());
            $this->assertEquals(['foo' => 'bar'], $content['data']);

            if (empty($ids)) {
                $streamer->cancel(); // break out of the listener loop
            }
        };

        $streamer->asConsumer('foobar', 'bar');
        $streamer->startFrom('0-0');
        $streamer->listen('foo.bar', $callback);
    }

    public function test_streamer_listen_logs_error_on_event_handle_throwable(): void
    {
        $this->app['config']->set('streamer.listen_timeout', 1);
        $this->app['config']->set('streamer.stream_read_timeout', 0.01);
        $streamer = new Streamer(new EventHistory());
        $event = $this->makeEvent();

        $id = $streamer->emit($event);
        Log::shouldReceive('error')
            ->once()
            ->with("Listener error. Failed processing message with ID {$id} on '{$event->name()}' stream. Error: error");

        $callback = function ($message) {
            throw new \Exception('error');
        };

        $streamer->startFrom('0-0');
        $streamer->listen('foo.bar', $callback);
    }

    public function test_streamer_listen_second_loop_is_not_started(): void
    {
        $this->app['config']->set('streamer.listen_timeout', 1);
        $this->app['config']->set('streamer.stream_read_timeout', 0.01);
        $streamer = new Streamer(new EventHistory());
        $event = $this->makeEvent();
        $id = $streamer->emit($event);
        $callback = function ($message, $streamer) use ($id) {
            $streamer->listen('bar.foo', function ($message, $streamer) {
            });
            $content = $message->getContent();
            $this->assertInstanceOf(ReceivedMessage::class, $message);
            $this->assertInstanceOf(Streamer::class, $streamer);
            $this->assertNotEmpty($content);
            $this->assertEquals($id, $message->getId());
            $this->assertEquals(['foo' => 'bar'], $content['data']);
        };

        $streamer->startFrom('0-0');
        $streamer->listen('foo.bar', $callback);
    }
}
