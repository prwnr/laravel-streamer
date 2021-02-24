<?php

namespace Prwnr\Streamer\Errors;

use Carbon\Carbon;
use JsonSerializable;
use Prwnr\Streamer\Stream;

/**
 * Class FailedMessage
 */
class FailedMessage implements JsonSerializable
{
    /**
     * @var string
     */
    private $id;
    /**
     * @var string
     */
    private $stream;
    /**
     * @var string
     */
    private $receiver;
    /**
     * @var string
     */
    private $error;
    /**
     * @var string
     */
    private $date;

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