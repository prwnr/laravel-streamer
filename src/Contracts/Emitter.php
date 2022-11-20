<?php

namespace Prwnr\Streamer\Contracts;

interface Emitter
{
    /**
     * Emits events onto the Stream.
     */
    public function emit(Event $event, string $id = '*'): string;
}
