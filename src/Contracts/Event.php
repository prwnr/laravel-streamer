<?php

declare(strict_types=1);

namespace Prwnr\Streamer\Contracts;

interface Event
{
    public const TYPE_EVENT = 'event';

    public const TYPE_NOTIFICATION = 'notification';

    public const TYPE_COMMAND = 'command';

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
     */
    public function payload(): array;
}
