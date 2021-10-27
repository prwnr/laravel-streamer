<?php

namespace Prwnr\Streamer\Errors;

use Carbon\Carbon;
use JsonSerializable;
use Prwnr\Streamer\Exceptions\MessageRetryFailedException;
use Prwnr\Streamer\Stream;
use Prwnr\Streamer\Stream\Range;

/**
 * Class FailedMessage
 */
class FailedMessage implements JsonSerializable
{
    private string $id;
    private string $stream;
    private string $receiver;
    private string $error;
    private string $date;

    /**
     * FailedMessage constructor.
     *
     * @param  string  $id
     * @param  string  $stream
     * @param  string  $receiver
     * @param  string  $error
     * @param  string|null  $date
     */
    public function __construct(string $id, string $stream, string $receiver, string $error, ?string $date = null)
    {
        $this->id = $id;
        $this->stream = $stream;
        $this->receiver = $receiver;
        $this->error = $error;
        $this->date = $date ?? Carbon::now()->toDateTimeString();
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return Stream
     */
    public function getStream(): Stream
    {
        return new Stream($this->stream);
    }

    /**
     * @return string
     */
    public function getReceiver(): string
    {
        return $this->receiver;
    }

    /**
     * @return string
     */
    public function getError(): string
    {
        return $this->error;
    }

    /**
     * @return string
     */
    public function getDate(): string
    {
        return $this->date;
    }

    /**
     * Returns stream message for the given failed message.
     *
     * @return array
     * @throws MessageRetryFailedException
     */
    public function getStreamMessage(): array
    {
        $range = new Range($this->getId(), $this->getId());
        $stream = $this->getStream();
        $messages = $stream->readRange($range, 1);

        if (!$messages || count($messages) !== 1) {
            throw new MessageRetryFailedException($this,
                "No matching messages found on a '{$stream->getName()}' stream for ID #{$this->getId()}.");
        }

        return array_pop($messages);
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'stream' => $this->stream,
            'receiver' => $this->receiver,
            'error' => $this->error,
            'date' => $this->date,
        ];
    }
}