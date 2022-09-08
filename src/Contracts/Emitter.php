<?php

namespace Prwnr\Streamer\Contracts;

/**
 * Interface Emitter.
 */
interface Emitter
{
    /**
     * Emits events onto the Stream
     */
    public function emit(Event $event, string $id = '*'): string;
}
