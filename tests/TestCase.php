<?php

namespace Tests;

use Exception;
use Prwnr\Streamer\Contracts\Event;
use Prwnr\Streamer\Contracts\StreamableMessage;
use Prwnr\Streamer\Errors\MessagesErrorHandler;
use Prwnr\Streamer\EventDispatcher\Message;
use Prwnr\Streamer\EventDispatcher\ReceivedMessage;
use Prwnr\Streamer\Facades\Streamer;
use Prwnr\Streamer\Stream;
use Prwnr\Streamer\StreamerProvider;
use Tests\Stubs\LocalListener;
use Tests\Stubs\MessageStub;
use Tests\Stubs\StreamerEventStub;
use Tests\Stubs\StreamerReplayableEventStub;

class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            StreamerProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Streamer' => Streamer::class,
        ];
    }

    protected function makeMessage(): StreamableMessage
    {
        return new MessageStub();
    }

    protected function makeEvent(): Event
    {
        return new StreamerEventStub();
    }

    protected function failFakeMessage(string $stream, string $id, array $data): ReceivedMessage
    {
        /** @var MessagesErrorHandler $handler */
        $handler = $this->app->make(MessagesErrorHandler::class);
        $message = new ReceivedMessage($id, [
            'name' => $stream,
            'data' => json_encode($data)
        ]);
        $listener = new LocalListener();
        $e = new Exception('error');
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
