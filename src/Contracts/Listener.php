<?php

namespace Prwnr\Streamer\Contracts;

/**
 * Interface Listener.
 */
interface Listener
{
    /**
     * @param  string|array  $events  name or multiple names of events (streams) that should be listened.
     * @param  callable|array  $handlers  are fired when message is read from stream (old one or a new one).
     * If one handler is passed it will be used for all the events that are being listened to.
     * For multiple handler, the format should be: [stream => handler] - one handler for one stream.
     *
     */
    public function listen($events, $handlers): void;
}
