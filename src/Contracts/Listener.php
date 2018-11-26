<?php

namespace Prwnr\Streamer\Contracts;

/**
 * Interface Listener
 * @package Prwnr\Streamer\Contracts
 */
interface Listener
{

    /**
     * @param string $event name
     * @param callable $handler is fired when message is read from stream (old one or a new one)
     * @return mixed
     */
    public function listen(string $event, callable $handler): void;
}