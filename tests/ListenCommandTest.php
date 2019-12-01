<?php

namespace Tests;

use Illuminate\Foundation\Testing\Concerns\InteractsWithRedis;
use Illuminate\Support\Facades\Log;
use Prwnr\Streamer\EventDispatcher\ReceivedMessage;
use Prwnr\Streamer\Facades\Streamer;
use Prwnr\Streamer\ListenersStack;
use Prwnr\Streamer\Stream;
use Tests\Stubs\AnotherLocalListener;
use Tests\Stubs\ExceptionalListener;
use Tests\Stubs\LocalListener;
use Tests\Stubs\NotReceiverListener;

class ListenCommandTest extends TestCase
{
    use InteractsWithRedis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRedis();
        $this->app['config']->set('streamer.listen_timeout', 0.01);
        $this->app['config']->set('streamer.stream_read_timeout', 0.01);
    }

    protected function tearDown(): void
    {
        $this->redis['predis']->connection()->flushall();
        $this->tearDownRedis();
        parent::tearDown();
    }

    public function test_command_called_without_listeners_configured_exits_with_error(): void
    {
        Streamer::emit($this->makeEvent());

        $this->artisan('streamer:listen', ['event' => 'foo.bar'])
            ->expectsOutput('There are no local listeners associated with foo.bar event in configuration.')
            ->assertExitCode(1);
    }

    public function test_command_called_with_listener_not_properly_created_outputs_error_for_it(): void
    {
        $listeners = [
            LocalListener::class,
            AnotherLocalListener::class,
            NotReceiverListener::class
        ];

        $this->withLocalListenersConfigured($listeners);
        $this->doesntExpectListenersToBeCalled([
            NotReceiverListener::class
        ]);
        $this->expectsListenersToBeCalled([
            LocalListener::class,
            AnotherLocalListener::class,
        ]);

        Streamer::emit($this->makeEvent());

        $this->artisan('streamer:listen', ['event' => 'foo.bar', '--last_id' => '0-0'])
            ->expectsOutput(sprintf('Listener class [%s] needs to implement MessageReceiver', NotReceiverListener::class))
            ->assertExitCode(0);
    }

    public function test_command_called_with_no_events_on_stream_exits_after_idle_time(): void
    {
        $listeners = [
            LocalListener::class,
            AnotherLocalListener::class,
        ];
        $this->withLocalListenersConfigured($listeners);

        $this->doesntExpectListenersToBeCalled($listeners);
        $this->artisan('streamer:listen', ['event' => 'foo.bar', '--last_id' => '0-0'])
            ->assertExitCode(0);
    }

    public function test_command_prints_out_the_error_when_it_occurs_on_listening(): void
    {
        $listeners = [ExceptionalListener::class];
        $this->withLocalListenersConfigured($listeners);

        $event = $this->makeEvent();
        $id = Streamer::emit($event);

        $error = "Listener error. Failed processing message with ID {$id} on '{$event->name()}' stream. Error: Listener failed.";
        Log::shouldReceive('error')->with($error);

        $this->artisan('streamer:listen', ['event' => 'foo.bar', '--last_id' => '0-0'])
            ->expectsOutput($error)
            ->assertExitCode(0);
    }

    public function test_command_called_with_events_on_stream_fires_local_event(): void
    {
        $listeners = [
            LocalListener::class,
            AnotherLocalListener::class,
        ];
        $this->withLocalListenersConfigured($listeners);
        $id = Streamer::emit($this->makeEvent());

        $this->expectsListenersToBeCalled($listeners);
        $this->artisan('streamer:listen', ['event' => 'foo.bar', '--last_id' => '0-0'])
            ->expectsOutput(sprintf("Processed message [$id] on 'foo.bar' stream by [%s] listener.", LocalListener::class))
            ->expectsOutput(sprintf("Processed message [$id] on 'foo.bar' stream by [%s] listener.", AnotherLocalListener::class))
            ->assertExitCode(0);
    }

    public function test_command_called_as_group_and_consumer_fires_local_event_and_ackowledges_message(): void
    {
        $listeners = [
            LocalListener::class,
            AnotherLocalListener::class,
        ];
        $this->withLocalListenersConfigured($listeners);
        $stream = new Stream('foo.bar');
        $stream->createGroup('bar');
        Streamer::emit($this->makeEvent());
        $args = [
            'event'      => 'foo.bar',
            '--last_id'  => '0-0',
            '--group'    => 'bar',
            '--consumer' => 'foobar',
        ];

        $this->expectsListenersToBeCalled($listeners);
        $this->artisan('streamer:listen', $args)
            ->assertExitCode(0);
    }

    public function test_command_called_as_group_adds_consumer_to_listener_automatically(): void
    {
        $listeners = [
            LocalListener::class,
            AnotherLocalListener::class,
        ];
        $this->withLocalListenersConfigured($listeners);
        $stream = new Stream('foo.bar');
        $stream->createGroup('bar');
        Streamer::emit($this->makeEvent());
        $args = [
            'event'     => 'foo.bar',
            '--last_id' => '0-0',
            '--group'   => 'bar',
        ];

        $this->expectsListenersToBeCalled($listeners);
        $this->artisan('streamer:listen', $args)
            ->assertExitCode(0);
    }

    public function test_command_called_as_group_with_not_existing_group_creates_group_automatically(): void
    {
        $listeners = [
            LocalListener::class,
            AnotherLocalListener::class,
        ];
        $this->withLocalListenersConfigured($listeners);
        Streamer::emit($this->makeEvent());
        $args = [
            'event'      => 'foo.bar',
            '--last_id'  => '0-0',
            '--group'    => 'bar',
            '--consumer' => 'foobar',
        ];

        $this->expectsListenersToBeCalled($listeners);
        $this->artisan('streamer:listen', $args)
            ->expectsOutput('Created new group: bar on a stream: foo.bar')
            ->assertExitCode(0);
    }

    public function test_command_called_with_reclaim_option_claims_all_pending_messages(): void
    {
        $listeners = [
            LocalListener::class,
            AnotherLocalListener::class,
        ];
        $this->withLocalListenersConfigured($listeners);
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

        $this->expectsListenersToBeCalled($listeners);
        $this->artisan('streamer:listen', $args)
            ->assertExitCode(0);
    }

    public function test_command_called_with_reclaim_option_does_not_claim_messages_older_than_idle_time(): void
    {
        $listeners = [
            LocalListener::class,
            AnotherLocalListener::class,
        ];
        $this->withLocalListenersConfigured($listeners);
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

        $this->doesntExpectListenersToBeCalled($listeners);
        $this->artisan('streamer:listen', $args)
            ->assertExitCode(0);
    }

    public function test_command_called_with_reclaim_option_does_not_claim_messages_when_none_is_on_stream(): void
    {
        $listeners = [
            LocalListener::class,
            AnotherLocalListener::class,
        ];
        $this->withLocalListenersConfigured($listeners);
        $args = [
            'event'      => 'foo.bar',
            '--last_id'  => '0-0',
            '--group'    => 'bar',
            '--consumer' => 'foobarB',
            '--reclaim'  => '10000',
        ];

        $this->doesntExpectListenersToBeCalled($listeners);
        $this->artisan('streamer:listen', $args)
            ->assertExitCode(0);
    }

    private function withLocalListenersConfigured(array $listeners): void
    {
        foreach ($listeners as $listener) {
            ListenersStack::add('foo.bar', $listener);
        }
    }

    private function expectsListenersToBeCalled(array $listeners): void
    {
        foreach ($listeners as $listener) {
            $mock = \Mockery::mock($listener);
            $mock->shouldReceive('handle')->with(ReceivedMessage::class);
            $this->app->instance($listener, $mock);
        }
    }

    private function doesntExpectListenersToBeCalled(array $listeners): void
    {
        foreach ($listeners as $listener) {
            $mock = \Mockery::mock($listener);
            $mock->shouldNotReceive('handle');
            $this->app->instance($listener, $mock);
        }
    }
}
