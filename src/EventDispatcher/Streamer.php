<?php

namespace Prwnr\Streamer\EventDispatcher;

use Illuminate\Support\Facades\Log;
use Prwnr\Streamer\Contracts\Emitter;
use Prwnr\Streamer\Contracts\Event;
use Prwnr\Streamer\Contracts\History;
use Prwnr\Streamer\Contracts\Listener;
use Prwnr\Streamer\Contracts\Replayable;
use Prwnr\Streamer\Contracts\Waitable;
use Prwnr\Streamer\History\Snapshot;
use Prwnr\Streamer\Stream;
use Throwable;

/**
 * Class Streamer.
 */
class Streamer implements Emitter, Listener
{
    /**
     * @var string
     */
    protected $startFrom;

    /**
     * Milliseconds.
     *
     * @var int
     */
    protected $readTimeout;

    /**
     * Milliseconds.
     *
     * @var int
     */
    protected $listenTimeout;

    /**
     * Seconds.
     *
     * @var int
     */
    protected $readSleep;

    /**
     * @var string
     */
    private $group;

    /**
     * @var string
     */
    private $consumer;

    /**
     * @var bool
     */
    private $canceled = false;

    /**
     * @var bool
     */
    private $inLoop = false;

    /**
     * @var History
     */
    private $history;

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
        $this->readTimeout = config('streamer.stream_read_timeout', 0);
        $this->listenTimeout = config('streamer.listen_timeout', 0);
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
     * {@inheritdoc}
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
     * {@inheritdoc}
     * @throws Throwable
     */
    public function listen(string $event, callable $handler): void
    {
        if ($this->inLoop) {
            return;
        }

        $stream = new Stream($event);

        try {
            if (!$this->group || !$this->consumer) {
                $this->listenOn($stream, $handler);
                return;
            }

            $this->adjustGroupReadTimeout();
            $this->listenOn(new Stream\Consumer($this->consumer, $stream, $this->group), $handler);
        } catch (Throwable $e) {
            throw $e;
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
     * @param  Waitable  $on
     * @param  callable  $handler
     */
    private function listenOn(Waitable $on, callable $handler): void
    {
        $start = microtime(true) * 1000;
        $lastSeenId = $this->startFrom ?? $on->getNewEntriesKey();
        while (!$this->canceled) {
            $this->inLoop = true;
            $payload = $on->await($lastSeenId, $this->readTimeout);
            if (!$payload) {
                if ($on->getNewEntriesKey() === Stream\Consumer::NEW_ENTRIES) {
                    $lastSeenId = $on->getNewEntriesKey();
                }
                sleep($this->readSleep);
                if ($this->shouldStop($start)) {
                    break;
                }
                continue;
            }

            $lastSeenId = $this->processPayload($payload, $on, $handler);
            $lastSeenId = $lastSeenId ?: $on->getNewEntriesKey();
            $start = microtime(true) * 1000;
        }
    }

    /**
     * @param  array  $payload
     * @param  Waitable  $on
     * @param  callable  $handler
     *
     * @return string
     */
    private function processPayload(array $payload, Waitable $on, callable $handler): ?string
    {
        $messageId = null;
        foreach ($payload[$on->getName()] as $messageId => $message) {
            try {
                $this->forward($messageId, $message, $handler);
                $on->acknowledge($messageId);
                if ($this->canceled) {
                    break;
                }
            } catch (Throwable $ex) {
                $this->report($messageId, $on, $ex);
                continue;
            }
        }

        return $messageId;
    }

    /**
     * @param  string  $messageId
     * @param  array  $message
     * @param  callable  $handler
     */
    private function forward(string $messageId, array $message, callable $handler): void
    {
        $handler(new ReceivedMessage($messageId, $message), $this);
    }

    /**
     * When listening on group, timeout should not be equal to 0, because it is required to know
     * when reading history of message is finished and when listener should start
     * reading only new messages via '>' key.
     */
    private function adjustGroupReadTimeout(): void
    {
        if ($this->readTimeout === 0) {
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
        if ($this->listenTimeout === 0) {
            return false;
        }

        if (microtime(true) * 1000 - $start > $this->listenTimeout) {
            return true;
        }

        return false;
    }

    /**
     * @param  string  $id
     * @param  Waitable  $on
     * @param  Throwable  $ex
     */
    private function report(string $id, Waitable $on, Throwable $ex): void
    {
        $error = "Listener error. Failed processing message with ID {$id} on '{$on->getName()}' stream. Error: {$ex->getMessage()}";
        Log::error($error);
    }
}
