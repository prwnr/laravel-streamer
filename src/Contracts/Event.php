<?php

namespace Prwnr\Streamer\Contracts;

/**
 * Interface Event.
 */
interface Event
{
    public const TYPE_EVENT = 'event';
    public const TYPE_NOTIFICATION = 'notification';
    public const TYPE_COMMAND = 'command';

    /**
     * Event name. Can be any string
     * This name will be later used as event name for listening.
     *
     * @return string
     */
    public function name(): string;

    /**
     * Event type. Can be one of the predefined types from this contract.
     *
     * @return string
     */
    public function type(): string;

    /**
     * Event payload that will be sent as message to Stream.
     *
     * @return array
     */
    public function payload(): array;
}
