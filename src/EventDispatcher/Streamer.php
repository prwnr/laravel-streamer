<?php

declare(strict_types=1);

namespace Prwnr\Streamer\EventDispatcher;

use Exception;
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

class Streamer implements Emitter, Listener
{
    protected string $startFrom;

    protected float $readTimeout;

    protected float $listenTimeout;

    protected float $readSleep;

    private string $group = '';

    private string $consumer = '';

    private bool $canceled = false;

    private bool $inLoop = false;

    /**
     * Listener constructor.
     */
    public function __construct(private readonly History $history)
    {
        $this->readTimeout = (float) config('streamer.stream_read_timeout', 1.0);
        $this->listenTimeout = (float) config('streamer.listen_timeout', 1.0);
        $this->readSleep = (float) config('streamer.read_sleep', 1.0);
        $this->readTimeout *= 1000.0;
        $this->listenTimeout *= 1000.0;
    }

    public function startFrom(string $startFrom): self
    {
        $this->startFrom = $startFrom;

        return $this;
    }

    public function asConsumer(string $consumer, string $group): self
    {
        $this->consumer = $consumer;
        $this->group = $group;

        return $this;
    }

    /**
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
     * and with \Prwnr\Streamer\EventDispatcher\Streamer as second argument.
     *
     * @inheritdoc
     *
     * @throws Throwable
     */
    public function listen(string|array $events, array|callable $handlers): void
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
     * @throws Exception
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
     * When listening on group, timeout should not be equal to 0, because it is required to know
     * when reading history of message is finished and when listener should start
     * reading only new messages via '>' key.
     */
    private function adjustGroupReadTimeout(): void
    {
        if ($this->readTimeout === 0.0) {
            $this->readTimeout = 2000.0;
        }
    }

    private function listenOn(Stream\MultiStream $streams, array $handlers): void
    {
        $start = microtime(true) * 1000;
        $lastSeenId = $this->startFrom ?? $streams->getNewEntriesKey();
        while (!$this->canceled) {
            $this->inLoop = true;
            $payload = $streams->await($lastSeenId, $this->readTimeout);
            if (!$payload) {
                $lastSeenId = $streams->getNewEntriesKey();
                sleep((int) $this->readSleep);
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

    private function shouldStop(float $start): bool
    {
        if ($this->listenTimeout === 0.0) {
            return false;
        }
        return microtime(true) * 1000 - $start > $this->listenTimeout;
    }

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
     * @throws JsonException
     */
    private function forward(array $message, callable $handler): void
    {
        $handler(new ReceivedMessage($message['id'], $message['message']), $this);
    }

    private function getHandler(string $stream, array $handlers): callable
    {
        if (count($handlers) === 1) {
            return Arr::first($handlers);
        }

        return $handlers[$stream];
    }

    private function report(string $id, Stream $on, Throwable $ex): void
    {
        $error = "Listener error. Failed processing message with ID $id on '{$on->getName()}' stream. Error: {$ex->getMessage()}";
        Log::error($error);
    }
}
