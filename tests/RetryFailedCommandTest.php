<?php

declare(strict_types=1);

namespace Tests;

use Carbon\Carbon;
use Exception;
use Illuminate\Foundation\Testing\Concerns\InteractsWithRedis;
use Prwnr\Streamer\Concerns\ConnectsWithRedis;
use Prwnr\Streamer\Contracts\Archiver;
use Prwnr\Streamer\Contracts\Errors\MessagesFailer;
use Prwnr\Streamer\Contracts\Errors\Repository;
use Prwnr\Streamer\Errors\FailedMessage;
use Prwnr\Streamer\Errors\MessagesRepository;
use Prwnr\Streamer\EventDispatcher\ReceivedMessage;
use Prwnr\Streamer\Exceptions\MessageRetryFailedException;
use Prwnr\Streamer\Stream;
use Tests\Stubs\AnotherLocalListener;
use Tests\Stubs\LocalListener;

class RetryFailedCommandTest extends TestCase
{
    use InteractsWithRedis;
    use ConnectsWithRedis;
    use WithMemoryManager;

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
        Carbon::withTestNow('2020-10-10', function (): void {
            $this->failFakeMessage('foo.bar', '123', ['payload' => 123]);
        });
        Carbon::withTestNow('2020-10-11', function (): void {
            $this->failFakeMessage('foo.bar', '345', ['payload' => 123]);
        });
        Carbon::withTestNow('2020-10-12', function (): void {
            $this->failFakeMessage('foo.bar', '678', ['payload' => 123]);
        });

        $this->expectsListenersToBeCalled([
            LocalListener::class,
        ]);

        $this->artisan('streamer:failed:retry', ['--all' => true])
            ->expectsOutput(
                sprintf(
                    'Successfully retried [123] on foo.bar stream by [%s] listener',
                    LocalListener::class
                )
            )
            ->expectsOutput(
                sprintf(
                    'Successfully retried [345] on foo.bar stream by [%s] listener',
                    LocalListener::class
                )
            )
            ->expectsOutput(
                sprintf(
                    'Successfully retried [678] on foo.bar stream by [%s] listener',
                    LocalListener::class
                )
            )
            ->assertExitCode(0);

        $this->assertEquals(0, $this->redis()->sCard(MessagesRepository::ERRORS_SET));
    }

    public function test_retries_message_by_id(): void
    {
        $this->failFakeMessage('foo.bar', '123', ['payload' => 123]);
        $this->failFakeMessage('foo.bar', '345', ['payload' => 123]);
        $this->failFakeMessage('foo.bar', '678', ['payload' => 123]);

        $this->expectsListenersToBeCalled([
            LocalListener::class,
        ]);

        $this->artisan('streamer:failed:retry', ['--id' => '123'])
            ->expectsOutput(
                sprintf(
                    'Successfully retried [123] on foo.bar stream by [%s] listener',
                    LocalListener::class
                )
            )
            ->assertExitCode(0);

        $this->assertEquals(2, $this->redis()->sCard(MessagesRepository::ERRORS_SET));
    }

    public function test_retries_message_by_stream(): void
    {
        $this->failFakeMessage('foo.bar', '123', ['payload' => 123]);
        $this->failFakeMessage('foo.bar', '345', ['payload' => 123]);
        $this->failFakeMessage('foo.other', '678', ['payload' => 123]);

        $this->expectsListenersToBeCalled([
            LocalListener::class,
        ]);

        $this->artisan('streamer:failed:retry', ['--stream' => 'foo.bar'])
            ->assertExitCode(0);

        $this->assertEquals(1, $this->redis()->sCard(MessagesRepository::ERRORS_SET));
    }

    public function test_retries_message_by_receiver(): void
    {
        Carbon::withTestNow('2020-10-11', function (): void {
            $this->failFakeMessage('foo.bar', '123', ['payload' => 123], new AnotherLocalListener());
        });
        Carbon::withTestNow('2020-10-12', function (): void {
            $this->failFakeMessage('foo.bar', '345', ['payload' => 123], new AnotherLocalListener());
        });
        $this->failFakeMessage('foo.bar', '678', ['payload' => 123]);

        $this->expectsListenersToBeCalled([
            LocalListener::class,
        ]);

        $this->artisan('streamer:failed:retry', ['--receiver' => AnotherLocalListener::class])
            ->expectsOutput(
                sprintf(
                    'Successfully retried [123] on foo.bar stream by [%s] listener',
                    AnotherLocalListener::class
                )
            )
            ->expectsOutput(
                sprintf(
                    'Successfully retried [345] on foo.bar stream by [%s] listener',
                    AnotherLocalListener::class
                )
            )
            ->assertExitCode(0);

        $this->assertEquals(1, $this->redis()->sCard(MessagesRepository::ERRORS_SET));
    }

    public function test_retries_message_by_combined_flags(): void
    {
        $this->failFakeMessage('foo.bar', '123', ['payload' => 123], new AnotherLocalListener());
        $this->failFakeMessage('foo.bar', '345', ['payload' => 123], new AnotherLocalListener());
        $this->failFakeMessage('foo.other', '123', ['payload' => 123]);

        $this->expectsListenersToBeCalled([
            LocalListener::class,
        ]);

        $this->artisan('streamer:failed:retry', [
            '--id' => '123',
            '--stream' => 'foo.bar',
        ])->expectsOutput(
            sprintf(
                'Successfully retried [123] on foo.bar stream by [%s] listener',
                AnotherLocalListener::class
            )
        )
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
            '--receiver' => AnotherLocalListener::class,
        ])->expectsOutput(
            sprintf(
                'Successfully retried [123] on foo.bar stream by [%s] listener',
                AnotherLocalListener::class
            )
        )
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
            ->expectsOutput(
                'Failed to retry [123] on foo.bar stream by [Tests\Stubs\LocalListener] listener. Error: errored again'
            )
            ->assertExitCode(0);
    }

    public function test_retries_failed_message_and_purges_it_from_stream(): void
    {
        $stream = new Stream('foo.bar');

        $this->failFakeMessage('foo.bar', '123', ['payload' => 123]);
        $this->failFakeMessage('foo.bar', '345', ['payload' => 123]);

        $this->assertCount(2, $stream->read()['foo.bar']);

        $this->expectsListenersToBeCalled([
            LocalListener::class,
        ]);

        $this->artisan('streamer:failed:retry', ['--id' => '123', '--purge' => true])
            ->expectsOutput(
                sprintf(
                    'Successfully retried [123] on foo.bar stream by [%s] listener',
                    LocalListener::class
                )
            )
            ->expectsOutput("Message [123] has been purged from the 'foo.bar' stream.")
            ->assertExitCode(0);

        $this->assertEquals(1, $this->redis()->sCard(MessagesRepository::ERRORS_SET));
        $this->assertCount(1, $stream->read()['foo.bar']);
    }

    public function test_retries_failed_message_but_wont_purge_it_until_last_fail_will_be_retried(): void
    {
        $stream = new Stream('foo.bar');

        $this->failFakeMessage('foo.bar', '123', ['payload' => 123], new LocalListener());
        $this->failFakeMessage('foo.bar', '123', ['payload' => 123], new AnotherLocalListener());

        $this->assertCount(1, $stream->read()['foo.bar']);

        $this->expectsListenersToBeCalled([
            LocalListener::class,
        ]);

        $this->artisan(
            'streamer:failed:retry',
            ['--id' => '123', '--receiver' => LocalListener::class, '--purge' => true]
        )
            ->expectsOutput(
                sprintf(
                    'Successfully retried [123] on foo.bar stream by [%s] listener',
                    LocalListener::class
                )
            )
            ->assertExitCode(0);

        $this->assertEquals(1, $this->redis()->sCard(MessagesRepository::ERRORS_SET));
        $this->assertCount(1, $stream->read()['foo.bar']);

        $this->expectsListenersToBeCalled([
            AnotherLocalListener::class,
        ]);

        $this->artisan(
            'streamer:failed:retry',
            ['--id' => '123', '--receiver' => AnotherLocalListener::class, '--purge' => true]
        )
            ->expectsOutput(
                sprintf(
                    'Successfully retried [123] on foo.bar stream by [%s] listener',
                    AnotherLocalListener::class
                )
            )
            ->expectsOutput("Message [123] has been purged from the 'foo.bar' stream.")
            ->assertExitCode(0);

        $this->assertEquals(0, $this->redis()->sCard(MessagesRepository::ERRORS_SET));
        $this->assertCount(0, $stream->read());
    }

    public function test_retries_failed_message_and_archives_it(): void
    {
        $this->setUpMemoryManager();
        $stream = new Stream('foo.bar');

        $this->failFakeMessage('foo.bar', '123', ['payload' => 123]);
        $this->failFakeMessage('foo.bar', '345', ['payload' => 123]);

        $this->assertCount(2, $stream->read()['foo.bar']);

        $this->expectsListenersToBeCalled([
            LocalListener::class,
        ]);

        $this->artisan('streamer:failed:retry', ['--id' => '123', '--archive' => true])
            ->expectsOutput(
                sprintf(
                    'Successfully retried [123] on foo.bar stream by [%s] listener',
                    LocalListener::class
                )
            )
            ->expectsOutput("Message [123] has been archived from the 'foo.bar' stream.")
            ->assertExitCode(0);

        $this->assertEquals(1, $this->redis()->sCard(MessagesRepository::ERRORS_SET));
        $this->assertCount(1, $stream->read()['foo.bar']);
        $this->assertNotNull($this->manager->driver('memory')->find('foo.bar', '123'));
    }

    public function test_retries_failed_message_but_wont_archive_it_until_last_fail_will_be_retried(): void
    {
        $this->setUpMemoryManager();
        $stream = new Stream('foo.bar');

        $this->failFakeMessage('foo.bar', '123', ['payload' => 123], new LocalListener());
        $this->failFakeMessage('foo.bar', '123', ['payload' => 123], new AnotherLocalListener());

        $this->assertCount(1, $stream->read()['foo.bar']);

        $this->expectsListenersToBeCalled([
            LocalListener::class,
        ]);

        $this->artisan(
            'streamer:failed:retry',
            ['--id' => '123', '--receiver' => LocalListener::class, '--archive' => true]
        )
            ->expectsOutput(
                sprintf(
                    'Successfully retried [123] on foo.bar stream by [%s] listener',
                    LocalListener::class
                )
            )
            ->assertExitCode(0);

        $this->assertEquals(1, $this->redis()->sCard(MessagesRepository::ERRORS_SET));
        $this->assertCount(1, $stream->read()['foo.bar']);
        $this->assertNull($this->manager->driver('memory')->find('foo.bar', '123'));

        $this->expectsListenersToBeCalled([
            AnotherLocalListener::class,
        ]);

        $this->artisan(
            'streamer:failed:retry',
            ['--id' => '123', '--receiver' => AnotherLocalListener::class, '--archive' => true]
        )
            ->expectsOutput(
                sprintf(
                    'Successfully retried [123] on foo.bar stream by [%s] listener',
                    AnotherLocalListener::class
                )
            )
            ->expectsOutput("Message [123] has been archived from the 'foo.bar' stream.")
            ->assertExitCode(0);

        $this->assertEquals(0, $this->redis()->sCard(MessagesRepository::ERRORS_SET));
        $this->assertCount(0, $stream->read());
        $this->assertNotNull($this->manager->driver('memory')->find('foo.bar', '123'));
    }

    public function test_retries_failed_message_but_fails_to_archive(): void
    {
        $this->setUpMemoryManager();
        $stream = new Stream('foo.bar');

        $this->failFakeMessage('foo.bar', '123', ['payload' => 123]);
        $this->assertCount(1, $stream->read()['foo.bar']);

        $this->expectsListenersToBeCalled([
            LocalListener::class,
        ]);

        $mock = $this->mock(Archiver::class);
        $mock->shouldReceive('archive')
            ->with(ReceivedMessage::class)
            ->andThrow(Exception::class, 'Something went wrong');

        $this->artisan('streamer:failed:retry', ['--id' => '123', '--archive' => true])
            ->expectsOutput(
                sprintf(
                    'Successfully retried [123] on foo.bar stream by [%s] listener',
                    LocalListener::class
                )
            )
            ->expectsOutput(
                "Message [123] from the 'foo.bar' stream could not be archived. Error: Something went wrong"
            )
            ->assertExitCode(0);

        $this->assertEquals(0, $this->redis()->sCard(MessagesRepository::ERRORS_SET));
        $this->assertCount(1, $stream->read()['foo.bar']);
        $this->assertNull($this->manager->driver('memory')->find('foo.bar', '123'));
    }
}
