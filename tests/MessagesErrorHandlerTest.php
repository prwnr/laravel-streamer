<?php

namespace Tests;

use Illuminate\Foundation\Testing\Concerns\InteractsWithRedis;
use Prwnr\Streamer\Concerns\ConnectsWithRedis;
use Prwnr\Streamer\EventDispatcher\Message;
use Prwnr\Streamer\EventDispatcher\ReceivedMessage;
use Prwnr\Streamer\MessagesErrorHandler;
use Prwnr\Streamer\Stream;
use Tests\Stubs\LocalListener;

class MessagesErrorHandlerTest extends TestCase
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
        $handler = new MessagesErrorHandler();
        $message = new ReceivedMessage('123', [
            'name' => 'foo.bar',
            'data' => json_encode('payload', JSON_THROW_ON_ERROR)
        ]);
        $listener = new LocalListener();
        $e = new \Exception('error');
        $handler->handle($message, $listener, $e);
        $failed = $this->redis()->lPop('failed_streams');

        $this->assertNotFalse($failed);

        $actual = json_decode($failed, true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals([
            'id' => $message->getId(),
            'stream' => 'foo.bar',
            'receiver' => LocalListener::class,
            'error' => 'error',
        ], $actual);
    }

    public function test_retries_failed_message(): void
    {
        $message = $this->failFakeMessage('foo.bar', '123', ['payload' => 123]);

        $listener = $this->mock(LocalListener::class);
        $listener->shouldReceive('handle')
            ->withArgs(static function ($arg) use ($message) {
                return $arg instanceof ReceivedMessage
                    && $arg->getId() && $message->getId()
                    && $arg->getContent() && $message->getContent();
            })
            ->once()
            ->andReturn();

        $handler = new MessagesErrorHandler();
        $handler->retry(new Stream('foo.bar'), '123', LocalListener::class);
    }

    public function test_retries_multiple_failed_messages(): void
    {
        $firstMessage = $this->failFakeMessage('foo.bar', '123', ['payload' => 123]);
        $secondMessage = $this->failFakeMessage('foo.bar', '345', ['payload' => 'foobar']);

        $listener = $this->mock(LocalListener::class);
        $listener->shouldReceive('handle')
            ->withArgs(static function ($arg) use ($firstMessage) {
                return $arg instanceof ReceivedMessage
                    && $arg->getId() && $firstMessage->getId()
                    && $arg->getContent() && $firstMessage->getContent();
            })
            ->once()
            ->andReturn();

        $listener->shouldReceive('handle')
            ->withArgs(static function ($arg) use ($secondMessage) {
                return $arg instanceof ReceivedMessage
                    && $arg->getId() && $secondMessage->getId()
                    && $arg->getContent() && $secondMessage->getContent();
            })
            ->once()
            ->andReturn();

        $handler = new MessagesErrorHandler();
        $handler->retryAll();
    }

    public function test_wont_retry_message_when_receiver_doest_not_exists(): void
    {
        $listener = $this->mock(LocalListener::class);
        $listener->shouldNotHaveBeenCalled();

        $handler = new MessagesErrorHandler();
        $handler->retry(new Stream('foo.bar'), '123', 'not a class');
    }

    public function test_wont_retry_message_when_it_doest_not_exists(): void
    {
        $listener = $this->mock(LocalListener::class);
        $listener->shouldNotHaveBeenCalled();

        $handler = new MessagesErrorHandler();
        $handler->retry(new Stream('foo.bar'), '123', LocalListener::class);
    }

    protected function failFakeMessage(string $stream, string $id, array $data): ReceivedMessage
    {
        $handler = new MessagesErrorHandler();
        $message = new ReceivedMessage($id, [
            'name' => $stream,
            'data' => json_encode($data, JSON_THROW_ON_ERROR)
        ]);
        $listener = new LocalListener();
        $e = new \Exception('error');
        $handler->handle($message, $listener, $e);

        $meta = [
            '_id' => $id,
            'name' => $stream,
            'domain' => 'test',
        ];
        $stream = new Stream($stream);
        $stream->add(new Message($meta, $data), $id);

        return $message;
    }
}