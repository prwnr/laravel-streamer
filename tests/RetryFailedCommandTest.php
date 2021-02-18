<?php

namespace Tests;

use Illuminate\Foundation\Testing\Concerns\InteractsWithRedis;
use Prwnr\Streamer\Concerns\ConnectsWithRedis;
use Prwnr\Streamer\Errors\MessagesRepository;
use Tests\Stubs\AnotherLocalListener;
use Tests\Stubs\LocalListener;

class RetryFailedCommandTest extends TestCase
{
    use InteractsWithRedis;
    use ConnectsWithRedis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRedis();
        $this->redis['phpredis']->connection()->flushall();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->redis['phpredis']->connection()->flushall();
        $this->tearDownRedis();
    }

    public function test_calling_retry_command_with_no_failed_messages_stored(): void
    {
        $this->artisan('streamer:failed:retry')
            ->expectsOutput('There are no failed messages to retry.')
            ->assertExitCode(0);
    }

    public function test_calling_retry_command_without_flags(): void
    {
        $this->failFakeMessage('foo.bar', '123', ['payload' => 123]);

        $this->artisan('streamer:failed:retry')
            ->expectsOutput('No retry option has been selected')
            ->expectsOutput("Use '--all' flag or at least one of '--all', '--stream', '--receiver'")
            ->assertExitCode(0);
    }

    public function test_retries_all_failed_messages(): void
    {
        $this->failFakeMessage('foo.bar', '123', ['payload' => 123]);
        $this->failFakeMessage('foo.bar', '345', ['payload' => 123]);
        $this->failFakeMessage('foo.bar', '678', ['payload' => 123]);

        $this->expectsListenersToBeCalled([
            LocalListener::class
        ]);

        $this->artisan('streamer:failed:retry', ['--all' => true])
            ->assertExitCode(0);

        $this->assertEquals(0, $this->redis()->sCard(MessagesRepository::ERRORS_SET));
    }

    public function test_retries_message_by_id(): void
    {
        $this->failFakeMessage('foo.bar', '123', ['payload' => 123]);
        $this->failFakeMessage('foo.bar', '345', ['payload' => 123]);
        $this->failFakeMessage('foo.bar', '678', ['payload' => 123]);

        $this->expectsListenersToBeCalled([
            LocalListener::class
        ]);

        $this->artisan('streamer:failed:retry', ['--id' => '123'])
            ->assertExitCode(0);

        $this->assertEquals(2, $this->redis()->sCard(MessagesRepository::ERRORS_SET));
    }

    public function test_retries_message_by_stream(): void
    {
        $this->failFakeMessage('foo.bar', '123', ['payload' => 123]);
        $this->failFakeMessage('foo.bar', '345', ['payload' => 123]);
        $this->failFakeMessage('foo.other', '678', ['payload' => 123]);

        $this->expectsListenersToBeCalled([
            LocalListener::class
        ]);

        $this->artisan('streamer:failed:retry', ['--stream' => 'foo.bar'])
            ->assertExitCode(0);

        $this->assertEquals(1, $this->redis()->sCard(MessagesRepository::ERRORS_SET));
    }

    public function test_retries_message_by_receiver(): void
    {
        $this->failFakeMessage('foo.bar', '123', ['payload' => 123], new AnotherLocalListener());
        $this->failFakeMessage('foo.bar', '345', ['payload' => 123], new AnotherLocalListener());
        $this->failFakeMessage('foo.bar', '678', ['payload' => 123]);

        $this->expectsListenersToBeCalled([
            LocalListener::class
        ]);

        $this->artisan('streamer:failed:retry', ['--receiver' => AnotherLocalListener::class])
            ->assertExitCode(0);

        $this->assertEquals(1, $this->redis()->sCard(MessagesRepository::ERRORS_SET));
    }

    public function test_retries_message_by_combined_flags(): void
    {
        $this->failFakeMessage('foo.bar', '123', ['payload' => 123], new AnotherLocalListener());
        $this->failFakeMessage('foo.bar', '345', ['payload' => 123], new AnotherLocalListener());
        $this->failFakeMessage('foo.other', '123', ['payload' => 123]);

        $this->expectsListenersToBeCalled([
            LocalListener::class
        ]);

        $this->artisan('streamer:failed:retry', [
            '--id' => '123',
            '--stream' => 'foo.bar',
        ])->assertExitCode(0);

        $this->assertEquals(2, $this->redis()->sCard(MessagesRepository::ERRORS_SET));
    }

    public function test_retries_message_by_all_flags(): void
    {
        $this->failFakeMessage('foo.bar', '123', ['payload' => 123], new AnotherLocalListener());
        $this->failFakeMessage('foo.bar', '345', ['payload' => 123], new AnotherLocalListener());
        $this->failFakeMessage('foo.other', '123', ['payload' => 123]);

        $this->expectsListenersToBeCalled([AnotherLocalListener::class]);

        $this->artisan('streamer:failed:retry', [
            '--id' => '123',
            '--stream' => 'foo.bar',
            '--receiver' => AnotherLocalListener::class
        ])->assertExitCode(0);

        $this->assertEquals(2, $this->redis()->sCard(MessagesRepository::ERRORS_SET));
    }

    public function test_calling_retry_command_with_no_message_matching_criteria(): void
    {
        $this->failFakeMessage('foo.bar', '345', ['payload' => 123], new AnotherLocalListener());

        $this->artisan('streamer:failed:retry', ['--id' => '123'])
            ->expectsOutput('There are no failed messages matching your criteria.')
            ->assertExitCode(0);
    }
}