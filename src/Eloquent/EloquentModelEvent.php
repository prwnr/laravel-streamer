<?php

namespace Prwnr\Streamer\Eloquent;

use Prwnr\Streamer\Contracts\Event;

/**
 * Class EloquentModelEvent.
 */
class EloquentModelEvent implements Event
{
    private string $name;
    private array $payload;

    /**
     * EloquentModelEvent constructor.
     * @param string $name
     * @param array $payload
     */
    public function __construct(string $name, array $payload)
    {
        $this->name = $name;
        $this->payload = $payload;
    }

    /**
     * Event name. Can be any string
     * This name will be later used as event name for listening.
     * @return string
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Event type. Can be one of the predefined types from this contract.
     * @return string
     */
    public function type(): string
    {
        return Event::TYPE_EVENT;
    }

    /**
     * Event payload that will be sent as message to Stream.
     * @return array
     */
    public function payload(): array
    {
        return $this->payload;
    }

}