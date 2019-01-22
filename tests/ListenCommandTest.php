<?php

namespace Tests;

use Illuminate\Foundation\Testing\Concerns\InteractsWithRedis;
use Prwnr\Streamer\Facades\Streamer;
use Prwnr\Streamer\Stream;
use Tests\Stubs\AnotherLocalEventStub;
use Tests\Stubs\LocalEventStub;

class ListenCommandTest extends TestCase
{
    use InteractsWithRedis;

    private $events = [
        LocalEventStub::class,
        AnotherLocalEventStub::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRedis();
        $this->app['config']->set('streamer.listen_timeout', 0.01);
        $this->app['config']->set('streamer.stream_read_timeout', 0.01);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->redis['predis']->connection()->flushall();
        $this->tearDownRedis();
    }

    public function test_command_called_without_events_configured_exits_with_error(): void
    {
        Streamer::emit($this->makeEvent());

        $this->artisan('streamer:listen', ['event' => 'foo.bar'])
            ->expectsOutput('There are no local events associated with foo.bar event in configuration.')
            ->assertExitCode(1);
    }

    public function test_command_called_with_no_events_on_stream_exits_after_idle_time(): void
    {
        $this->withLocalEventsConfigured();

        $this->doesntExpectEvents($this->events);
        $this->artisan('streamer:listen', ['event' => 'foo.bar', '--last_id' => '0-0'])
            ->assertExitCode(0);
    }

    public function test_command_called_with_events_on_stream_fires_local_event(): void
    {
        $this->withLocalEventsConfigured();
        Streamer::emit($this->makeEvent());

        $this->expectsEvents($this->events);
        $this->artisan('streamer:listen', ['event' => 'foo.bar', '--last_id' => '0-0'])
            ->assertExitCode(0);
    }

    public function test_command_called_as_group_and_consumer_fires_local_event_and_ackowledges_message(): void
    {
        $this->withLocalEventsConfigured();
        $stream = new Stream('foo.bar');
        $stream->createGroup('bar');
        Streamer::emit($this->makeEvent());
        $args = [
            'event'      => 'foo.bar',
            '--last_id'  => '0-0',
            '--group'    => 'bar',
            '--consumer' => 'foobar',
        ];

        $this->expectsEvents($this->events);
        $this->artisan('streamer:listen', $args)
            ->assertExitCode(0);
    }

    public function test_command_called_as_group_adds_consumer_to_listener_automatically(): void
    {
        $this->withLocalEventsConfigured();
        $stream = new Stream('foo.bar');
        $stream->createGroup('bar');
        Streamer::emit($this->makeEvent());
        $args = [
            'event'     => 'foo.bar',
            '--last_id' => '0-0',
            '--group'   => 'bar',
        ];

        $this->expectsEvents($this->events);
        $this->artisan('streamer:listen', $args)
            ->assertExitCode(0);
    }

    public function test_command_called_as_group_with_not_existing_group_creates_group_automatically(): void
    {
        $this->withLocalEventsConfigured();
        Streamer::emit($this->makeEvent());
        $args = [
            'event'      => 'foo.bar',
            '--last_id'  => '0-0',
            '--group'    => 'bar',
            '--consumer' => 'foobar',
        ];

        $this->expectsEvents($this->events);
        $this->artisan('streamer:listen', $args)
            ->expectsOutput('Created new group: bar on a stream: foo.bar')
            ->assertExitCode(0);
    }

    public function test_command_called_with_reclaim_option_claims_all_pending_messages(): void
    {
        $this->withLocalEventsConfigured();
        $stream = new Stream('foo.bar');
        $stream->createGroup('bar');
        $consumer = new Stream\Consumer('foobarA', $stream, 'bar');
        Streamer::emit($this->makeEvent());
        //Consume messages without acknowledging them, so that they will stays as pending
        $consumer->await($consumer->getNewEntriesKey());
        $args = [
            'event'      => 'foo.bar',
            '--last_id'  => '0-0',
            '--group'    => 'bar',
            '--consumer' => 'foobarB',
            '--reclaim'  => '1',
        ];

        $this->expectsEvents($this->events);
        $this->artisan('streamer:listen', $args)
            ->assertExitCode(0);
    }

    public function test_command_called_with_reclaim_option_does_not_claim_messages_older_than_idle_time(): void
    {
        $this->withLocalEventsConfigured();
        $stream = new Stream('foo.bar');
        $stream->createGroup('bar');
        $consumer = new Stream\Consumer('foobarA', $stream, 'bar');
        Streamer::emit($this->makeEvent());
        //Consume messages without acknowledging them, so that they will stays as pending
        $consumer->await($consumer->getNewEntriesKey());
        $args = [
            'event'      => 'foo.bar',
            '--last_id'  => '0-0',
            '--group'    => 'bar',
            '--consumer' => 'foobarB',
            '--reclaim'  => '10000',
        ];

        $this->doesntExpectEvents($this->events);
        $this->artisan('streamer:listen', $args)
            ->assertExitCode(0);
    }

    public function test_command_called_with_reclaim_option_does_not_claim_messages_when_none_is_on_stream(): void
    {
        $this->withLocalEventsConfigured();
        $args = [
            'event'      => 'foo.bar',
            '--last_id'  => '0-0',
            '--group'    => 'bar',
            '--consumer' => 'foobarB',
            '--reclaim'  => '10000',
        ];

        $this->doesntExpectEvents($this->events);
        $this->artisan('streamer:listen', $args)
            ->assertExitCode(0);
    }

    private function withLocalEventsConfigured(): void
    {
        $this->app['config']->set('streamer.listen_and_fire', [
            'foo.bar' => $this->events,
        ]);
    }
}
