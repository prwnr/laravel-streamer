<?php

declare(strict_types=1);

namespace Prwnr\Streamer\Errors;

use Carbon\Carbon;
use JsonSerializable;
use Prwnr\Streamer\Exceptions\MessageRetryFailedException;
use Prwnr\Streamer\Stream;
use Prwnr\Streamer\Stream\Range;

class FailedMessage implements JsonSerializable
{
    private readonly string $date;

    public function __construct(
        private readonly string $id,
        private readonly string $stream,
        private readonly string $receiver,
        private readonly string $error,
        ?string $date = null
    ) {
        $this->date = $date ?? Carbon::now()->toDateTimeString();
    }

    public function getReceiver(): string
    {
        return $this->receiver;
    }

    public function getError(): string
    {
        return $this->error;
    }

    public function getDate(): string
    {
        return $this->date;
    }

    /**
     * Returns stream message for the given failed message.
     *
     * @throws MessageRetryFailedException
     */
    public function getStreamMessage(): array
    {
        $range = new Range($this->getId(), $this->getId());
        $stream = $this->getStream();
        $messages = $stream->readRange($range, 1);

        if (!$messages || count($messages) !== 1) {
            throw new MessageRetryFailedException(
                $this,
                "No matching messages found on a '{$stream->getName()}' stream for ID #{$this->getId()}."
            );
        }

        return array_pop($messages);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getStream(): Stream
    {
        return new Stream($this->stream);
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
