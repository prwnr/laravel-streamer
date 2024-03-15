<?php

namespace Prwnr\Streamer\Contracts;

interface Emitter
{
    public function emit(Event $event, string $id = '*'): string;
}
