<?php


namespace Prwnr\Streamer\Contracts;

/**
 * Interface Event
 * @package Prwnr\Streamer\Contracts
 */
interface Event
{
    public const TYPE_EVENT = 'event';
    public const TYPE_NOTIFICATION = 'notification';
    public const TYPE_COMMAND = 'command';

    /**
     * Name of event
     * String will be transformed to 'dot.case'
     * @return string
     */
    public function name(): string;

    /**
     * Eventy type. Can be one of the predefined types from this contract
     * @return string
     */
    public function type(): string;

    /**
     * Event payload that will be sent as message to Stream
     * @return array
     */
    public function payload(): array;
}