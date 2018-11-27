<?php

namespace Prwnr\Streamer\EventDispatcher;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Prwnr\Streamer\Contracts\{
    Emitter, Event, Listener, Waitable
};
use Prwnr\Streamer\Stream;

/**
 * Class Streamer
 * @package Prwnr\Streamer
 */
class Streamer implements Emitter, Listener
{

    /**
     * @var string
     */
    protected $startFrom;

    /**
     * @var int
     */
    protected $timeout;

    /**
     * @var string
     */
    private $group;

    /**
     * @var string
     */
    private $consumer;

    /**
     * @param string $startFrom
     * @return Streamer
     */
    public function startFrom(string $startFrom): self
    {
        $this->startFrom = $startFrom;

        return $this;
    }

    /**
     * Listener constructor.
     */
    public function __construct()
    {
        $this->timeout = config('streamer.listen_timeout') ?: 0;
    }

    /**
     * @param string $consumer
     * @param string $group
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
    public function emit(Event $event): string
    {
        $meta = [
            'type' => $event->type(),
            'domain' => config('streamer.domain'),
            'name' => $event->name(),
            'created' => time()
        ];

        $message = new Message($meta, $event->payload());
        $stream = new Stream($event->name());
        return $stream->add($message);
    }

    /**
     * Handler is invoked with \Prwnr\Streamer\EventDispatcher\ReceivedMessage instance as argument
     * {@inheritdoc}
     */
    public function listen(string $event, callable $handler): void
    {
        $stream = new Stream($event);
        if ($this->group && $this->consumer) {
            $this->adjustGroupListenerTimeout();
            $this->listenOn(new Stream\Consumer($this->consumer, $stream, $this->group), $handler);
            return;
        }

        $this->listenOn($stream, $handler);
    }

    /**
     * @param Waitable $on
     * @param callable $handler
     */
    private function listenOn(Waitable $on, callable $handler): void
    {
        $lastSeenId = $this->startFrom ?? $on->getNewEntriesKey();
        while (true) {
            $payload = $on->await($lastSeenId, $this->timeout);
            if (!$payload) {
                if ($on->getNewEntriesKey() === Stream\Consumer::NEW_ENTRIES) {
                    $lastSeenId = $on->getNewEntriesKey();
                }
                sleep(1);
                continue;
            }

            $messageId = null;
            foreach ($payload[$on->getName()] as $messageId => $message) {
                try {
                    $this->forward($messageId, $message, $handler);
                    $on->acknowledge($messageId);
                } catch (\Throwable $ex) {
                    Log::error("Listener error. Failed processing message with ID {$messageId} on '{$on->getName()}' stream. Error: {$ex->getMessage()}");
                    continue;
                }
            }

            $lastSeenId = $messageId ?: $on->getNewEntriesKey();
        }
    }

    /**
     * @param string $messageId
     * @param array $message
     * @param callable $handler
     */
    private function forward(string $messageId, array $message, callable $handler): void
    {
        $handler(new ReceivedMessage($messageId, $message));
    }

    /**
     * When listening on group, timeout should not equal 0, because it is required to know
     * when reading history of message is finished and when listening for only new messages
     * should be started via '>' key
     */
    private function adjustGroupListenerTimeout()
    {
        if ($this->timeout === 0) {
            $this->timeout = 2000;
        }
    }
}