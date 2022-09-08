<?php

namespace Prwnr\Streamer\Eloquent;

use Prwnr\Streamer\Contracts\Event;

/**
 * Class EloquentModelEvent.
 */
class EloquentModelEvent implements Event
{
    /**
     * EloquentModelEvent constructor.
     */
    public function __construct(private readonly string $name, private readonly array $payload)
    {
    }

    /**
     * Event name. Can be any string
     * This name will be later used as event name for listening.
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Event type. Can be one of the predefined types from this contract.
     */
    public function type(): string
    {
        return Event::TYPE_EVENT;
    }

    /**
     * Event payload that will be sent as message to Stream.
     */
    public function payload(): array
    {
        return $this->payload;
    }

}