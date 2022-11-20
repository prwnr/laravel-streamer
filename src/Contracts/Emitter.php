<?php

declare(strict_types=1);

namespace Prwnr\Streamer\Contracts;

interface Emitter
{
    /**
     * Emits events onto the Stream.
     */
    public function emit(Event $event, string $id = '*'): string;
}
