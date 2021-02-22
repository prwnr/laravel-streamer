<?php

namespace Tests;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\Concerns\InteractsWithRedis;
use Prwnr\Streamer\Concerns\ConnectsWithRedis;
use Prwnr\Streamer\Contracts\Errors\MessagesFailer;
use Prwnr\Streamer\Contracts\Errors\Repository;
use Prwnr\Streamer\Errors\FailedMessage;
use Prwnr\Streamer\Errors\MessagesRepository;
use Prwnr\Streamer\Exceptions\MessageRetryFailedException;
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
        Carbon::withTestNow('2020-10-10', function () {
            $this->failFakeMessage('foo.bar', '123', ['payload' => 123]);
        });
        Carbon::withTestNow('2020-10-11', function () {
            $this->failFakeMessage('foo.bar', '345', ['payload' => 123]);
        });
        Carbon::withTestNow('2020-10-12', function () {
            $this->failFakeMessage('foo.bar', '678', ['payload' => 123]);
        });

        $this->expectsListenersToBeCalled([
            LocalListener::class
        ]);

        $this->artisan('streamer:failed:retry', ['--all' => true])
            ->expectsOutput(sprintf('Successfully retired [123] on foo.bar stream by [%s] listener',
                LocalListener::class))
            ->expectsOutput(sprintf('Successfully retired [345] on foo.bar stream by [%s] listener',
                LocalListener::class))
            ->expectsOutput(sprintf('Successfully retired [678] on foo.bar stream by [%s] listener',
                LocalListener::class))
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
            ->expectsOutput(sprintf('Successfully retired [123] on foo.bar stream by [%s] listener',
                LocalListener::class))
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
        Carbon::withTestNow('2020-10-11', function () {
            $this->failFakeMessage('foo.bar', '123', ['payload' => 123], new AnotherLocalListener());
        });
        Carbon::withTestNow('2020-10-12', function () {
            $this->failFakeMessage('foo.bar', '345', ['payload' => 123], new AnotherLocalListener());
        });
        $this->failFakeMessage('foo.bar', '678', ['payload' => 123]);

        $this->expectsListenersToBeCalled([
            LocalListener::class
        ]);

        $this->artisan('streamer:failed:retry', ['--receiver' => AnotherLocalListener::class])
            ->expectsOutput(sprintf('Successfully retired [123] on foo.bar stream by [%s] listener',
                AnotherLocalListener::class))
            ->expectsOutput(sprintf('Successfully retired [345] on foo.bar stream by [%s] listener',
                AnotherLocalListener::class))
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
        ])->expectsOutput(sprintf('Successfully retired [123] on foo.bar stream by [%s] listener',
            AnotherLocalListener::class))
            ->assertExitCode(0);

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
        ])->expectsOutput(sprintf('Successfully retired [123] on foo.bar stream by [%s] listener',
            AnotherLocalListener::class))
            ->assertExitCode(0);

        $this->assertEquals(2, $this->redis()->sCard(MessagesRepository::ERRORS_SET));
    }

    public function test_calling_retry_command_with_no_message_matching_criteria(): void
    {
        $this->failFakeMessage('foo.bar', '345', ['payload' => 123], new AnotherLocalListener());

        $this->artisan('streamer:failed:retry', ['--id' => '123'])
            ->expectsOutput('There are no failed messages matching your criteria.')
            ->assertExitCode(0);
    }

    public function test_calling_retry_command_with_message_failing_again(): void
    {
        $this->failFakeMessage('foo.bar', '123', ['payload' => 123]);
        /** @var Repository $repository */
        $repository = $this->app->make(Repository::class);
        $message = $repository->all()->first();

        $mock = $this->mock(MessagesFailer::class);
        $mock->shouldReceive('retry')
            ->with(FailedMessage::class)
            ->andThrow(new MessageRetryFailedException($message, 'errored again'));

        $this->artisan('streamer:failed:retry', ['--all' => true])
            ->expectsOutput('Failed to retry [123] on foo.bar stream by [Tests\Stubs\LocalListener] listener. Error: errored again')
            ->assertExitCode(0);
    }
}