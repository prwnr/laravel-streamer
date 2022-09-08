<?php

namespace Prwnr\Streamer\Contracts;

/**
 * Interface Event.
 */
interface Event
{
    final public const TYPE_EVENT = 'event';
    final public const TYPE_NOTIFICATION = 'notification';
    final public const TYPE_COMMAND = 'command';

    /**
     * Event name. Can be any string
     * This name will be later used as event name for listening.
     */
    public function name(): string;

    /**
     * Event type. Can be one of the predefined types from this contract.
     */
    public function type(): string;

    /**
     * Event payload that will be sent as message to Stream.
     *
     * @return array
     */
    public function payload(): array;
}
