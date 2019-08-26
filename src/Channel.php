<?php

namespace Prwnr\Streamer;

use Closure;
use Illuminate\Support\Str;
use Predis\Response\ServerException;
use Prwnr\Streamer\Contracts\Event;
use Prwnr\Streamer\EventDispatcher\Message;
use Prwnr\Streamer\EventDispatcher\ReceivedMessage;
use Prwnr\Streamer\Stream\Consumer;

/**
 * Class Channel
 */
abstract class Channel
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var Stream
     */
    private $stream;

    /**
     * @var Consumer
     */
    private $consumer;

    /**
     * @var int
     */
    private $timeout = 0;

    /**
     * Channel constructor.
     * @throws ServerException
     */
    public function __construct()
    {
        $this->name = Str::snake(class_basename($this), '.');
        $this->stream = new Stream($this->name);
        if (!$this->stream->groupExists($this->getGroup())) {
            $this->stream->createGroup($this->getGroup());
        }

        $this->consumer = new Consumer($this->getConsumer(), $this->stream, $this->getGroup());
    }

    /**
     * @param  int  $timeout
     * @return Channel
     */
    public function setTimeout(int $timeout): self
    {
        $this->timeout = $timeout;

        return $this;
    }

    /**
     * Returns name of the group on which channel will be listening.
     *
     * @return string
     */
    abstract public function getGroup(): string;

    /**
     * Returns name of the consumer with which channel will be listening.
     *
     * @return string
     */
    abstract public function getConsumer(): string;

    /**
     * Receive message from stream as a consumer.
     *
     * @param  Closure  $callback  returns true|false determining whether listening should be stopped or not.
     * @throws \Exception
     */
    public function receive(Closure $callback): void
    {
        $receive = true;
        while ($receive) {
            $result = $this->consumer->await(Consumer::NEW_ENTRIES, $this->timeout);
            if (empty($result)) {
                $message = new ReceivedMessage('', ['data' => null]);
                $receive = $callback($message);
                continue;
            }

            foreach ($result[$this->name] as $id => $data) {
                $message = new ReceivedMessage($id, $data);
                $receive = $callback($message);
                $this->consumer->acknowledge($id);
            }
        }
    }

    /**
     * Sends message to the stream with event name based on class name (as dot notation).
     *
     * @param  array  $payload
     */
    public function send(array $payload): void
    {
        $meta = [
            'type' => Event::TYPE_COMMAND,
            'domain' => config('streamer.domain'),
            'name' => $this->name,
            'created' => time(),
        ];

        $message = new Message($meta, $payload);
        $this->stream->add($message);
    }
}