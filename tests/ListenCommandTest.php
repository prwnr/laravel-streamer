<?php

declare(strict_types=1);

namespace Tests;

use Exception;
use Illuminate\Foundation\Testing\Concerns\InteractsWithRedis;
use Prwnr\Streamer\Contracts\Archiver;
use Prwnr\Streamer\Errors\MessagesRepository;
use Prwnr\Streamer\EventDispatcher\ReceivedMessage;
use Prwnr\Streamer\Facades\Streamer;
use Prwnr\Streamer\ListenersStack;
use Prwnr\Streamer\Stream;
use Tests\Stubs\AnotherLocalListener;
use Tests\Stubs\ExceptionalListener;
use Tests\Stubs\FooBarStreamerEventStub;
use Tests\Stubs\LocalListener;
use Tests\Stubs\NotReceiverListener;
use Tests\Stubs\OtherBarStreamerEventStub;

class ListenCommandTest extends TestCase
{
    use InteractsWithRedis;
    use WithMemoryManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRedis();
        $this->redis['phpredis']->connection()->flushall();
        $this->app['config']->set('streamer.listen_timeout', 0.01);
        $this->app['config']->set('streamer.stream_read_timeout', 0.01);

        $this->setUpMemoryManager();
    }

    protected function tearDown(): void
    {
        $this->redis['phpredis']->connection()->flushall();
        $this->tearDownRedis();
        parent::tearDown();

        $this->manager = null;
    }

    public function test_command_called_without_listeners_configured_exits_with_error(): void
    {
        $this->artisan('streamer:listen', ['events' => 'foo.bar,other.bar'])
            ->expectsOutput("There are no local listeners associated with 'foo.bar' event in configuration.")
            ->expectsOutput("There are no local listeners associated with 'other.bar' event in configuration.")
            ->assertExitCode(1);
    }

    public function test_command_called_with_listener_not_properly_created_outputs_error_for_it(): void
    {
        $listeners = [
            LocalListener::class,
            AnotherLocalListener::class,
            NotReceiverListener::class,
        ];

        $this->withLocalListenersConfigured($listeners);
        $this->doesntExpectListenersToBeCalled([
            NotReceiverListener::class,
        ]);
        $this->expectsListenersToBeCalled([
            LocalListener::class,
            AnotherLocalListener::class,
        ]);

        Streamer::emit($this->makeEvent());

        $this->artisan('streamer:listen', ['events' => 'foo.bar', '--last_id' => '0-0'])
            ->expectsOutput(
                sprintf('Listener class [%s] needs to implement MessageReceiver', NotReceiverListener::class)
            )
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
        $this->artisan('streamer:listen', ['events' => 'foo.bar', '--last_id' => '0-0'])
            ->assertExitCode(0);
    }

    public function test_command_prints_out_the_error_when_it_occurs_on_listening_and_stores_failed_message_info(): void
    {
        $listeners = [ExceptionalListener::class];
        $this->withLocalListenersConfigured($listeners);

        $event = $this->makeEvent();
        $id = Streamer::emit($event);
        $printError = sprintf(
            "Listener error. Failed processing message with ID %s on '%s' stream by %s. Error: Listener failed.",
            $id,
            $event->name(),
            ExceptionalListener::class
        );

        $this->artisan('streamer:listen', ['events' => 'foo.bar', '--last_id' => '0-0'])
            ->expectsOutput($printError)
            ->assertExitCode(0);

        $repository = new MessagesRepository();
        $this->assertEquals(1, $repository->count());
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
        $this->artisan('streamer:listen', ['events' => 'foo.bar', '--last_id' => '0-0'])
            ->expectsOutput(
                sprintf(
                    "Processed message [$id] on 'foo.bar' stream by [%s] listener.",
                    LocalListener::class
                )
            )
            ->expectsOutput(
                sprintf(
                    "Processed message [$id] on 'foo.bar' stream by [%s] listener.",
                    AnotherLocalListener::class
                )
            )
            ->assertExitCode(0);
    }

    public function test_command_called_with_all_will_listen_and_handle_all_events_received(): void
    {
        ListenersStack::addMany([
            'foo.bar' => [
                LocalListener::class,
                AnotherLocalListener::class,
            ],
            'other.bar' => [
                LocalListener::class,
            ],
        ]);

        $fooId = Streamer::emit(new FooBarStreamerEventStub());
        $otherId = Streamer::emit(new OtherBarStreamerEventStub());

        $this->expectsListenersToBeCalled([
            LocalListener::class,
            AnotherLocalListener::class,
        ]);

        $this->artisan('streamer:listen', ['--all' => true, '--last_id' => '0-0'])
            ->expectsOutput(
                sprintf(
                    "Processed message [$fooId] on 'foo.bar' stream by [%s] listener.",
                    LocalListener::class
                )
            )
            ->expectsOutput(
                sprintf(
                    "Processed message [$fooId] on 'foo.bar' stream by [%s] listener.",
                    AnotherLocalListener::class
                )
            )
            ->expectsOutput(
                sprintf(
                    "Processed message [$otherId] on 'other.bar' stream by [%s] listener.",
                    LocalListener::class
                )
            )
            ->assertExitCode(0);
    }

    public function test_command_called_with_events_while_one_of_them_throws_exception_and_stores_failed_messages_info(
    ): void {
        $listeners = [
            ExceptionalListener::class,
            LocalListener::class,
        ];
        $this->withLocalListenersConfigured($listeners);

        $event = $this->makeEvent();
        $id = Streamer::emit($event);

        $printError = sprintf(
            "Listener error. Failed processing message with ID %s on '%s' stream by %s. Error: Listener failed.",
            $id,
            $event->name(),
            ExceptionalListener::class
        );

        $this->artisan('streamer:listen', ['events' => 'foo.bar', '--last_id' => '0-0'])
            ->expectsOutput($printError)
            ->expectsOutput(
                sprintf(
                    "Processed message [$id] on 'foo.bar' stream by [%s] listener.",
                    LocalListener::class
                )
            )
            ->assertExitCode(0);

        $repository = new MessagesRepository();
        $this->assertEquals(1, $repository->count());
    }

    public function test_command_called_as_group_and_consumer_fires_local_event_and_acknowledges_message(): void
    {
        $listeners = [
            LocalListener::class,
            AnotherLocalListener::class,
        ];
        $this->withLocalListenersConfigured($listeners);
        $stream = new Stream('foo.bar');
        $stream->createGroup('bar');
        Streamer::emit($this->makeEvent());
        $this->expectsListenersToBeCalled($listeners);
        $this->artisan('streamer:listen', [
            'events' => 'foo.bar',
            '--last_id' => '0-0',
            '--group' => 'bar',
            '--consumer' => 'foobar',
        ])
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
        $this->expectsListenersToBeCalled($listeners);
        $this->artisan('streamer:listen', [
            'events' => 'foo.bar',
            '--last_id' => '0-0',
            '--group' => 'bar',
        ])
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
        $this->expectsListenersToBeCalled($listeners);
        $this->artisan('streamer:listen', [
            'events' => 'foo.bar',
            '--last_id' => '0-0',
            '--group' => 'bar',
            '--consumer' => 'foobar',
        ])
            ->assertExitCode(0);

        $stream = new Stream('foo.bar');
        $this->assertTrue($stream->groupExists('bar'));
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
        $this->expectsListenersToBeCalled($listeners);
        $this->artisan('streamer:listen', [
            'events' => 'foo.bar',
            '--last_id' => '0-0',
            '--group' => 'bar',
            '--consumer' => 'foobarB',
            '--reclaim' => '1',
        ])
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
        $this->doesntExpectListenersToBeCalled($listeners);
        $this->artisan('streamer:listen', [
            'events' => 'foo.bar',
            '--last_id' => '0-0',
            '--group' => 'bar',
            '--consumer' => 'foobarB',
            '--reclaim' => '10000',
        ])
            ->assertExitCode(0);
    }

    public function test_command_called_with_reclaim_option_does_not_claim_messages_when_none_is_on_stream(): void
    {
        $listeners = [
            LocalListener::class,
            AnotherLocalListener::class,
        ];
        $this->withLocalListenersConfigured($listeners);
        $this->doesntExpectListenersToBeCalled($listeners);
        $this->artisan('streamer:listen', [
            'events' => 'foo.bar',
            '--last_id' => '0-0',
            '--group' => 'bar',
            '--consumer' => 'foobarB',
            '--reclaim' => '10000',
        ])
            ->assertExitCode(0);
    }

    public function test_command_is_killed_when_unexpected_non_listener_exception_occurs(): void
    {
        $this->withLocalListenersConfigured([LocalListener::class]);

        $mock = $this->mock(\Prwnr\Streamer\EventDispatcher\Streamer::class);
        $mock->shouldReceive('listen')
            ->andThrow(Exception::class, 'Error occurred');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Error occurred');

        $this->artisan('streamer:listen', ['events' => 'foo.bar']);
    }

    public function test_command_is_kept_alive_when_unexpected_non_listener_exception_occurs(): void
    {
        $this->withLocalListenersConfigured([LocalListener::class]);

        $mock = $this->mock(\Prwnr\Streamer\EventDispatcher\Streamer::class);
        $mock->shouldReceive('listen')
            ->once()
            ->andThrow(Exception::class, 'Error occurred');

        $mock->shouldReceive('listen')
            ->once()
            ->andReturn();

        $this->artisan('streamer:listen', ['events' => 'foo.bar', '--keep-alive' => true])
            ->expectsOutput('Error occurred')
            ->expectsOutput('Starting listener again due to unexpected error.')
            ->assertExitCode(0);
    }

    public function test_command_is_kept_alive_when_unexpected_non_listener_exception_occurs_with_maximum_attempts_limit(
    ): void {
        $this->withLocalListenersConfigured([LocalListener::class]);

        $mock = $this->mock(\Prwnr\Streamer\EventDispatcher\Streamer::class);
        $mock->shouldReceive('listen')
            ->times(3)
            ->andThrow(Exception::class, 'Error occurred');

        $this->artisan('streamer:listen', ['events' => 'foo.bar', '--keep-alive' => true, '--max-attempts' => 2])
            ->expectsOutput('Error occurred')
            ->expectsOutput('Starting listener again due to unexpected error.')
            ->expectsOutput('Attempts left: 2')
            ->expectsOutput('Error occurred')
            ->expectsOutput('Starting listener again due to unexpected error.')
            ->expectsOutput('Attempts left: 1')
            ->expectsOutput('Error occurred')
            ->assertExitCode(0);
    }

    public function test_command_called_with_purge_will_delete_messages_from_stream_on_success(): void
    {
        $listeners = [
            LocalListener::class,
            AnotherLocalListener::class,
        ];
        $this->withLocalListenersConfigured($listeners);
        $id = Streamer::emit($this->makeEvent());

        $this->expectsListenersToBeCalled($listeners);
        $this->artisan('streamer:listen', ['events' => 'foo.bar', '--last_id' => '0-0', '--purge' => true])
            ->expectsOutput(
                sprintf(
                    "Processed message [$id] on 'foo.bar' stream by [%s] listener.",
                    LocalListener::class
                )
            )
            ->expectsOutput(
                sprintf(
                    "Processed message [$id] on 'foo.bar' stream by [%s] listener.",
                    AnotherLocalListener::class
                )
            )
            ->expectsOutput("Message [$id] has been purged from the 'foo.bar' stream.")
            ->assertExitCode(0);

        $stream = new Stream('foo.bar');
        $this->assertCount(0, $stream->read());
    }

    public function test_command_called_with_purge_will_not_delete_messages_from_stream_on_at_least_one_failure(): void
    {
        $listeners = [
            ExceptionalListener::class,
            LocalListener::class,
        ];
        $this->withLocalListenersConfigured($listeners);

        $event = $this->makeEvent();
        $id = Streamer::emit($event);

        $printError = sprintf(
            "Listener error. Failed processing message with ID %s on '%s' stream by %s. Error: Listener failed.",
            $id,
            $event->name(),
            ExceptionalListener::class
        );

        $this->artisan('streamer:listen', ['events' => 'foo.bar', '--last_id' => '0-0', '--purge' => true])
            ->expectsOutput($printError)
            ->expectsOutput(
                sprintf(
                    "Processed message [$id] on 'foo.bar' stream by [%s] listener.",
                    LocalListener::class
                )
            )
            ->assertExitCode(0);

        $repository = new MessagesRepository();
        $this->assertEquals(1, $repository->count());

        $stream = new Stream('foo.bar');
        $this->assertCount(1, $stream->read()['foo.bar']);
    }

    public function test_command_called_with_archive_will_delete_messages_from_stream_and_store_in_different_storage(
    ): void {
        $listeners = [
            LocalListener::class,
            AnotherLocalListener::class,
        ];
        $this->withLocalListenersConfigured($listeners);
        $event = $this->makeEvent();
        $id = Streamer::emit($event);

        $this->expectsListenersToBeCalled($listeners);
        $this->artisan('streamer:listen', ['events' => 'foo.bar', '--last_id' => '0-0', '--archive' => true])
            ->expectsOutput(
                sprintf(
                    "Processed message [$id] on 'foo.bar' stream by [%s] listener.",
                    LocalListener::class
                )
            )
            ->expectsOutput(
                sprintf(
                    "Processed message [$id] on 'foo.bar' stream by [%s] listener.",
                    AnotherLocalListener::class
                )
            )
            ->expectsOutput("Message [$id] has been archived from the 'foo.bar' stream.")
            ->assertExitCode(0);

        $stream = new Stream('foo.bar');
        $this->assertCount(0, $stream->read());

        $message = $this->manager->driver('memory')->find('foo.bar', $id);
        $this->assertNotNull($message);
        $this->assertEquals($event->payload(), $message->getData());
    }

    public function test_command_called_with_archive_fails(): void
    {
        $listeners = [
            LocalListener::class,
            AnotherLocalListener::class,
        ];
        $this->withLocalListenersConfigured($listeners);
        $id = Streamer::emit($this->makeEvent());

        $mock = $this->mock(Archiver::class);
        $mock->shouldReceive('archive')
            ->with(ReceivedMessage::class)
            ->andThrow(Exception::class, 'Something went wrong');

        $this->expectsListenersToBeCalled($listeners);
        $this->artisan('streamer:listen', ['events' => 'foo.bar', '--last_id' => '0-0', '--archive' => true])
            ->expectsOutput(
                sprintf(
                    "Processed message [$id] on 'foo.bar' stream by [%s] listener.",
                    LocalListener::class
                )
            )
            ->expectsOutput(
                sprintf(
                    "Processed message [$id] on 'foo.bar' stream by [%s] listener.",
                    AnotherLocalListener::class
                )
            )
            ->expectsOutput(
                "Message [$id] from the 'foo.bar' stream could not be archived. Error: Something went wrong"
            )
            ->assertExitCode(0);

        $stream = new Stream('foo.bar');
        $this->assertCount(1, $stream->read()['foo.bar']);

        $this->assertNull($this->manager->driver('memory')->find('foo.bar', $id));
    }

    public function test_message_is_not_acknowledged_if_any_listener_fails_and_config_flag_is_on(): void
    {
        $this->app['config']->set('streamer.always_acknowledge', false);
        $listeners = [
            ExceptionalListener::class,
            LocalListener::class,
        ];
        $this->withLocalListenersConfigured($listeners);
        $stream = new Stream('foo.bar');
        $group = 'testgroup';
        $consumer = 'testconsumer';
        // 1. Create the group first
        $stream->createGroup($group, '0');
        // 2. Emit the message
        $event = $this->makeEvent();
        $id = Streamer::emit($event);
        // 3. Run the listen command with --last_id => '>'
        $this->artisan('streamer:listen', [
            'events' => 'foo.bar',
            '--last_id' => '>',
            '--group' => $group,
            '--consumer' => $consumer,
        ])
            ->assertExitCode(0);
        // 4. Assert the message is pending for the consumer
        $pending = $stream->pending($group, $consumer);
        $pendingIds = array_map(static fn($item) => $item[0], $pending);
        $this->assertContains($id, $pendingIds);
    }

    public function test_message_is_acknowledged_if_all_listeners_succeed_and_config_flag_is_on(): void
    {
        $this->app['config']->set('streamer.always_acknowledge', true);
        $listeners = [
            LocalListener::class,
            AnotherLocalListener::class,
        ];
        $this->withLocalListenersConfigured($listeners);
        $stream = new Stream('foo.bar');
        $group = 'testgroup';
        $consumer = 'testconsumer';
        $event = $this->makeEvent();
        $id = Streamer::emit($event);
        // Create the group with last-delivered-id '0' so all messages are delivered
        $stream->createGroup($group, '0');

        $this->artisan('streamer:listen', [
            'events' => 'foo.bar',
            '--last_id' => '0-0',
            '--group' => $group,
            '--consumer' => $consumer,
        ])
            ->assertExitCode(0);

        // Message should NOT be pending for the group/consumer
        $pending = $stream->pending($group, $consumer);
        $pendingIds = array_map(static fn($item) => $item[0], $pending);
        $this->assertNotContains($id, $pendingIds);
    }

    public function test_message_is_acknowledged_on_failure_if_config_flag_is_off(): void
    {
        $this->app['config']->set('streamer.always_acknowledge', false);
        $listeners = [
            ExceptionalListener::class,
            LocalListener::class,
        ];
        $this->withLocalListenersConfigured($listeners);
        $stream = new Stream('foo.bar');
        $group = 'testgroup';
        $consumer = 'testconsumer';
        $event = $this->makeEvent();
        $id = Streamer::emit($event);
        // Create the group with last-delivered-id '0' so all messages are delivered
        $stream->createGroup($group, '0');

        $this->artisan('streamer:listen', [
            'events' => 'foo.bar',
            '--last_id' => '0-0',
            '--group' => $group,
            '--consumer' => $consumer,
        ])
            ->assertExitCode(0);

        // Message should NOT be pending for the group/consumer (default behavior)
        $pending = $stream->pending($group, $consumer);
        $pendingIds = array_map(static fn($item) => $item[0], $pending);
        $this->assertNotContains($id, $pendingIds);
    }

    public function test_group_created_after_event_delivers_previous_events(): void
    {
        $listeners = [
            LocalListener::class,
        ];
        $this->withLocalListenersConfigured($listeners);
        $stream = new Stream('foo.bar');
        $group = 'lategroup1';
        $consumer = 'lateconsumer1';
        // 1. Emit the event before creating the group
        $event = $this->makeEvent();
        $id = Streamer::emit($event);
        // 2. Create the group at '0' (after the event)
        $stream->createGroup($group, '0');
        // 3. Run the listen command with --last_id => '>'
        $this->artisan('streamer:listen', [
            'events' => 'foo.bar',
            '--last_id' => '>',
            '--group' => $group,
            '--consumer' => $consumer,
        ])
            ->assertExitCode(0);
        // 4. Assert the message is NOT pending for the consumer (was acknowledged)
        $pending = $stream->pending($group, $consumer);
        $pendingIds = array_map(static fn($item) => $item[0], $pending);
        $this->assertNotContains($id, $pendingIds);
    }

    public function test_group_created_after_event_leaves_pending_on_failure(): void
    {
        $this->app['config']->set('streamer.always_acknowledge', false);
        $listeners = [
            ExceptionalListener::class,
        ];
        $this->withLocalListenersConfigured($listeners);
        $stream = new Stream('foo.bar');
        $group = 'lategroup2';
        $consumer = 'lateconsumer2';
        // 1. Emit the event before creating the group
        $event = $this->makeEvent();
        $id = Streamer::emit($event);
        // 2. Create the group at '0' (after the event)
        $stream->createGroup($group, '0');
        // 3. Run the listen command with --last_id => '>'
        $this->artisan('streamer:listen', [
            'events' => 'foo.bar',
            '--last_id' => '>',
            '--group' => $group,
            '--consumer' => $consumer,
        ])
            ->assertExitCode(0);
        // 4. Assert the message IS pending for the consumer (was not acknowledged)
        $pending = $stream->pending($group, $consumer);
        $pendingIds = array_map(static fn($item) => $item[0], $pending);
        $this->assertContains($id, $pendingIds);
    }

    public function test_create_group_with_zero_last_delivered_id_delivers_all_messages(): void
    {
        $listeners = [
            \Tests\Stubs\LocalListener::class,
        ];
        $this->withLocalListenersConfigured($listeners);
        $stream = new \Prwnr\Streamer\Stream('foo.bar');
        $group = 'testgroupzero';
        $consumer = 'testconsumerzero';
        // Emit multiple events before group creation
        $event1 = $this->makeEvent();
        $id1 = \Prwnr\Streamer\Facades\Streamer::emit($event1);
        $event2 = $this->makeEvent();
        $id2 = \Prwnr\Streamer\Facades\Streamer::emit($event2);
        // Create the group with last-delivered-id '0' so all messages are delivered
        $stream->createGroup($group, '0');
        $this->artisan('streamer:listen', [
            'events' => 'foo.bar',
            '--last_id' => '>',
            '--group' => $group,
            '--consumer' => $consumer,
        ])
            ->assertExitCode(0);
        // Assert both messages are NOT pending for the consumer (were acknowledged)
        $pending = $stream->pending($group, $consumer);
        $pendingIds = array_map(static fn($item) => $item[0], $pending);
        $this->assertNotContains($id1, $pendingIds);
        $this->assertNotContains($id2, $pendingIds);
    }
}
