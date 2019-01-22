<?php

namespace Prwnr\Streamer\Contracts;

/**
 * Interface Emitter.
 */
interface Emitter
{
    /**
     * @param Event $event
     *
     * @return string
     */
    public function emit(Event $event): string;
}
