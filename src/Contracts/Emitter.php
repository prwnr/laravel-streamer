<?php

namespace Prwnr\Streamer\Contracts;

/**
 * Interface Emitter.
 */
interface Emitter
{
    /**
     * @param  Event  $event
     *
     * @param  string  $id
     * @return string
     */
    public function emit(Event $event, string $id = '*'): string;
}
