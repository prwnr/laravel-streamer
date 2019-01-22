<?php

namespace Prwnr\Streamer\Contracts;

/**
 * Interface Listener.
 */
interface Listener
{
    /**
     * @param string   $event   name
     * @param callable $handler is fired when message is read from stream (old one or a new one)
     */
    public function listen(string $event, callable $handler): void;
}
