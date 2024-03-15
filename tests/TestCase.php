<?php

declare(strict_types=1);

namespace Tests;

use Exception;
use Mockery;
use Prwnr\Streamer\Contracts\Errors\StreamableMessage;
use Prwnr\Streamer\Contracts\Event;
use Prwnr\Streamer\Contracts\MessageReceiver;
use Prwnr\Streamer\Errors\FailedMessagesHandler;
use Prwnr\Streamer\EventDispatcher\Message;
use Prwnr\Streamer\EventDispatcher\ReceivedMessage;
use Prwnr\Streamer\Facades\Streamer;
use Prwnr\Streamer\ListenersStack;
use Prwnr\Streamer\Stream;
use Prwnr\Streamer\StreamerProvider;
use Tests\Stubs\FooBarStreamerEventStub;
use Tests\Stubs\LocalListener;
use Tests\Stubs\MessageStub;

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
        return new FooBarStreamerEventStub();
    }

    protected function withLocalListenersConfigured(array $listeners): void
    {
        foreach ($listeners as $listener) {
            ListenersStack::add('foo.bar', $listener);
        }
    }

    protected function expectsListenersToBeCalled(array $listeners): void
    {
        foreach ($listeners as $listener) {
            $mock = Mockery::mock($listener);
            $mock->shouldReceive('handle')->with(ReceivedMessage::class);
            $this->app->instance($listener, $mock);
        }
    }

    protected function doesntExpectListenersToBeCalled(array $listeners): void
    {
        foreach ($listeners as $listener) {
            $mock = Mockery::mock($listener);
            $mock->shouldNotReceive('handle');
            $this->app->instance($listener, $mock);
        }
    }

    protected function failFakeMessage(
        string $stream,
        string $id,
        array $data,
        ?MessageReceiver $listener = null
    ): ReceivedMessage {
        /** @var FailedMessagesHandler $handler */
        $handler = $this->app->make(FailedMessagesHandler::class);
        $message = new ReceivedMessage($id, [
            'name' => $stream,
            'data' => json_encode($data, JSON_THROW_ON_ERROR),
        ]);
        $listener ??= new LocalListener();
        $e = new Exception('error');
        $handler->store($message, $listener, $e);

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
