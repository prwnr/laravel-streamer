<?php

namespace Prwnr\Streamer\EventDispatcher;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use JsonException;
use Prwnr\Streamer\Contracts\Emitter;
use Prwnr\Streamer\Contracts\Event;
use Prwnr\Streamer\Contracts\History;
use Prwnr\Streamer\Contracts\Listener;
use Prwnr\Streamer\Contracts\Replayable;
use Prwnr\Streamer\Exceptions\InvalidListeningArgumentsException;
use Prwnr\Streamer\History\Snapshot;
use Prwnr\Streamer\Stream;
use Throwable;

/**
 * Class Streamer.
 */
class Streamer implements Emitter, Listener
{
    protected string $startFrom;
    protected float $readTimeout = 0.0;
    protected float $listenTimeout;
    protected float $readSleep;
    private string $group = '';
    private string $consumer = '';
    private bool $canceled = false;
    private bool $inLoop = false;
    private History $history;

    /**
     * @param  string  $startFrom
     *
     * @return Streamer
     */
    public function startFrom(string $startFrom): self
    {
        $this->startFrom = $startFrom;

        return $this;
    }

    /**
     * Listener constructor.
     *
     * @param  History  $history
     */
    public function __construct(History $history)
    {
        $this->readTimeout = config('streamer.stream_read_timeout', 1);
        $this->listenTimeout = config('streamer.listen_timeout', 1);
        $this->readSleep = config('streamer.read_sleep', 1);
        $this->readTimeout *= 1000;
        $this->listenTimeout *= 1000;

        $this->history = $history;
    }

    /**
     * @param  string  $consumer
     * @param  string  $group
     *
     * @return Streamer
     */
    public function asConsumer(string $consumer, string $group): self
    {
        $this->consumer = $consumer;
        $this->group = $group;

        return $this;
    }

    /**
     * @inheritdoc
     * @throws JsonException
     */
    public function emit(Event $event, string $id = '*'): string
    {
        $meta = [
            'type' => $event->type(),
            'domain' => config('streamer.domain'),
            'name' => $event->name(),
            'created' => time(),
        ];

        $message = new Message($meta, $event->payload());
        $stream = new Stream($event->name());

        $id = $stream->add($message, $id);

        if ($event instanceof Replayable) {
            $this->history->record(new Snapshot($id, $event));
        }

        return $id;
    }

    /**
     * Handler is invoked with \Prwnr\Streamer\EventDispatcher\ReceivedMessage instance as first argument
     * and with \Prwnr\Streamer\EventDispatcher\Streamer as second argument
     *
     * @inheritdoc
     *
     * @throws Throwable
     */
    public function listen($events, $handlers): void
    {
        if ($this->inLoop) {
            return;
        }

        [$events, $handlers] = $this->parseArgs($events, $handlers);

        if ($this->consumer && $this->group) {
            $this->adjustGroupReadTimeout();
        }

        try {
            $multiStream = new Stream\MultiStream($events, $this->group, $this->consumer);
            $this->listenOn($multiStream, $handlers);
        } finally {
            $this->inLoop = false;
        }
    }

    /**
     * Cancels current listener loop.
     */
    public function cancel(): void
    {
        $this->canceled = true;
    }

    /**
     * @param  Stream\MultiStream  $streams
     * @param  array  $handlers
     * @return void
     */
    private function listenOn(Stream\MultiStream $streams, array $handlers): void
    {
        $start = microtime(true) * 1000;
        $lastSeenId = $this->startFrom ?? $streams->getNewEntriesKey();
        while (!$this->canceled) {
            $this->inLoop = true;
            $payload = $streams->await($lastSeenId, $this->readTimeout);
            if (!$payload) {
                $lastSeenId = $streams->getNewEntriesKey();
                sleep($this->readSleep);
                if ($this->shouldStop($start)) {
                    break;
                }
                continue;
            }

            $this->processPayload($payload, $handlers, $streams);

            $lastSeenId = $streams->getNewEntriesKey();

            $start = microtime(true) * 1000;
        }
    }

    /**
     * @param  array  $payload
     * @param  array  $handlers
     * @param  Stream\MultiStream  $streams
     * @return void
     */
    private function processPayload(array $payload, array $handlers, Stream\MultiStream $streams): void
    {
        foreach ($payload as $message) {
            try {
                $this->forward($message, $this->getHandler($message['stream'], $handlers));
                $streams->acknowledge([$message['stream'] => $message['id']]);
                if ($this->canceled) {
                    break;
                }
            } catch (Throwable $ex) {
                $this->report($message['id'], $streams->streams()->get($message['stream']), $ex);
                continue;
            }
        }
    }

    /**
     * @param  array  $message
     * @param  callable  $handler
     * @return void
     * @throws JsonException
     */
    private function forward(array $message, callable $handler): void
    {
        $handler(new ReceivedMessage($message['id'], $message['message']), $this);
    }

    /**
     * @param $events
     * @param $handlers
     * @return array
     * @throws \Exception
     */
    private function parseArgs($events, $handlers): array
    {
        $eventsList = Arr::wrap($events);
        if (is_array($handlers) && count($eventsList) === 1 && count($handlers) > 1) {
            throw new InvalidListeningArgumentsException();
        }

        if (is_callable($handlers)) {
            return [$eventsList, [$handlers]];
        }

        foreach ($eventsList as $event) {
            if (!isset($handlers[$event])) {
                throw new InvalidListeningArgumentsException();
            }
        }

        return [$eventsList, $handlers];
    }

    /**
     * @param  string  $stream
     * @param  array  $handlers
     * @return callable
     */
    private function getHandler(string $stream, array $handlers): callable
    {
        if (count($handlers) === 1) {
            return Arr::first($handlers);
        }

        return $handlers[$stream];
    }

    /**
     * When listening on group, timeout should not be equal to 0, because it is required to know
     * when reading history of message is finished and when listener should start
     * reading only new messages via '>' key.
     */
    private function adjustGroupReadTimeout(): void
    {
        if ($this->readTimeout === 0.0) {
            $this->readTimeout = 2000;
        }
    }

    /**
     * @param  float  $start
     *
     * @return bool
     */
    private function shouldStop(float $start): bool
    {
        if ($this->listenTimeout === 0.0) {
            return false;
        }

        if (microtime(true) * 1000 - $start > $this->listenTimeout) {
            return true;
        }

        return false;
    }

    /**
     * @param  string  $id
     * @param  Stream  $on
     * @param  Throwable  $ex
     * @return void
     */
    private function report(string $id, Stream $on, Throwable $ex): void
    {
        $error = "Listener error. Failed processing message with ID $id on '{$on->getName()}' stream. Error: {$ex->getMessage()}";
        Log::error($error);
    }
}
