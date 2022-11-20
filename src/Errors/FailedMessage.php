<?php

namespace Prwnr\Streamer\Errors;

use Carbon\Carbon;
use JsonSerializable;
use Prwnr\Streamer\Exceptions\MessageRetryFailedException;
use Prwnr\Streamer\Stream;
use Prwnr\Streamer\Stream\Range;

class FailedMessage implements JsonSerializable
{
    public readonly string $date;

    public function __construct(
        public readonly string $id,
        public readonly string $stream,
        public readonly string $receiver,
        public readonly string $error,
        ?string $date = null
    ) {
        $this->date = $date ?? Carbon::now()->toDateTimeString();
    }

    public function getStreamInstance(): Stream
    {
        return new Stream($this->stream);
    }

    /**
     * Returns stream message for the given failed message.
     *
     * @throws MessageRetryFailedException
     */
    public function getStreamMessage(): array
    {
        $range = new Range($this->id, $this->id);
        $stream = $this->getStreamInstance();
        $messages = $stream->readRange($range, 1);

        if (!$messages || count($messages) !== 1) {
            throw new MessageRetryFailedException(
                $this,
                sprintf("No matching messages found on a '%s' stream for ID #%s.", $stream->getName(), $this->id)
            );
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
