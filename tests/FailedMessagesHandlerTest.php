<?php

declare(strict_types=1);

namespace Tests;

use Carbon\Carbon;
use Exception;
use Illuminate\Foundation\Testing\Concerns\InteractsWithRedis;
use Prwnr\Streamer\Concerns\ConnectsWithRedis;
use Prwnr\Streamer\Contracts\Errors\Repository;
use Prwnr\Streamer\Errors\FailedMessage;
use Prwnr\Streamer\Errors\FailedMessagesHandler;
use Prwnr\Streamer\Errors\MessagesRepository;
use Prwnr\Streamer\EventDispatcher\ReceivedMessage;
use Prwnr\Streamer\Exceptions\MessageRetryFailedException;
use Tests\Stubs\LocalListener;

class FailedMessagesHandlerTest extends TestCase
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

    public function test_stores_failed_message_information(): void
    {
        Carbon::setTestNow('2021-12-12 12:12:12');
        /** @var FailedMessagesHandler $handler */
        $handler = $this->app->make(FailedMessagesHandler::class);
        $message = new ReceivedMessage('123', [
            'name' => 'foo.bar',
            'data' => json_encode('payload', JSON_THROW_ON_ERROR),
        ]);
        $listener = new LocalListener();
        $e = new Exception('error');
        $handler->store($message, $listener, $e);
        $failed = $this->redis()->sMembers(MessagesRepository::ERRORS_SET);

        $this->assertNotEmpty($failed);
        $this->assertCount(1, $failed);

        $actual = json_decode((string) $failed[0], true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals([
            'id' => $message->getId(),
            'stream' => 'foo.bar',
            'receiver' => LocalListener::class,
            'error' => 'error',
            'date' => '2021-12-12 12:12:12',
        ], $actual);

        Carbon::setTestNow();
    }

    public function test_retries_failed_message(): void
    {
        $message = $this->failFakeMessage('foo.bar', '123', ['payload' => 123]);

        $listener = $this->mock(LocalListener::class);
        $listener->shouldReceive('handle')
            ->withArgs(static fn ($arg): bool => $arg instanceof ReceivedMessage
                && $arg->getId() && $message->getId()
                && $arg->getContent() && $message->getContent())
            ->once()
            ->andReturn();

        /** @var FailedMessagesHandler $handler */
        $handler = $this->app->make(FailedMessagesHandler::class);
        $handler->retry(new FailedMessage('123', 'foo.bar', LocalListener::class, 'error'));
    }

    public function test_retries_multiple_failed_messages(): void
    {
        $firstMessage = $this->failFakeMessage('foo.bar', '123', ['payload' => 123]);
        $secondMessage = $this->failFakeMessage('foo.bar', '345', ['payload' => 'foobar']);

        $this->assertEquals(2, $this->redis()->sCard(MessagesRepository::ERRORS_SET));

        $listener = $this->mock(LocalListener::class);
        $listener->shouldReceive('handle')
            ->withArgs(static fn ($arg): bool => $arg instanceof ReceivedMessage
                && $arg->getId() && $firstMessage->getId()
                && $arg->getContent() && $firstMessage->getContent())
            ->once()
            ->andReturn();

        $listener->shouldReceive('handle')
            ->withArgs(static fn ($arg): bool => $arg instanceof ReceivedMessage
                && $arg->getId() && $secondMessage->getId()
                && $arg->getContent() && $secondMessage->getContent())
            ->once()
            ->andReturn();

        /** @var FailedMessagesHandler $handler */
        $handler = $this->app->make(FailedMessagesHandler::class);
        /** @var Repository $repository */
        $repository = $this->app->make(Repository::class);
        foreach ($repository->all() as $message) {
            $handler->retry($message);
        }

        $this->assertEquals(0, $this->redis()->sCard(MessagesRepository::ERRORS_SET));
    }

    public function test_wont_retry_message_when_receiver_doest_not_exists(): void
    {
        $listener = $this->mock(LocalListener::class);
        $listener->shouldNotHaveBeenCalled();

        /** @var FailedMessagesHandler $handler */
        $handler = $this->app->make(FailedMessagesHandler::class);

        $this->expectException(MessageRetryFailedException::class);
        $this->expectExceptionMessage('Receiver class does not exists');

        $handler->retry(new FailedMessage('123', 'foo.bar', 'not a class', 'error'));
    }

    public function test_wont_retry_message_when_it_doest_not_exists(): void
    {
        $listener = $this->mock(LocalListener::class);
        $listener->shouldNotHaveBeenCalled();

        /** @var FailedMessagesHandler $handler */
        $handler = $this->app->make(FailedMessagesHandler::class);

        $this->expectException(MessageRetryFailedException::class);
        $this->expectExceptionMessage("No matching messages found on a 'foo.bar' stream for ID #123");

        $handler->retry(new FailedMessage('123', 'foo.bar', LocalListener::class, 'error'));
    }

    public function test_handles_failed_message_and_puts_it_back_when_it_fails_again(): void
    {
        $message = $this->failFakeMessage('foo.bar', '123', ['payload' => 123]);

        $listener = $this->mock(LocalListener::class);
        $listener->shouldReceive('handle')
            ->withArgs(static fn ($arg): bool => $arg instanceof ReceivedMessage
                && $arg->getId() && $message->getId()
                && $arg->getContent() && $message->getContent())
            ->once()
            ->andThrow(Exception::class, 'errored again');

        /** @var FailedMessagesHandler $handler */
        $handler = $this->app->make(FailedMessagesHandler::class);
        /** @var Repository $repository */
        $repository = $this->app->make(Repository::class);
        foreach ($repository->all() as $message) {
            try {
                $handler->retry($message);
            } catch (MessageRetryFailedException $e) {
                $error = 'Failed to retry [123] on foo.bar stream by [Tests\Stubs\LocalListener] listener. Error: errored again';
                $this->assertEquals($error, $e->getMessage());
            }
        }

        $this->assertEquals(1, $repository->count());
        $this->assertEquals($message->getId(), $repository->all()->first()->getId());
    }
}
