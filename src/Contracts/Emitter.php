<?php

namespace Prwnr\Streamer\Contracts;

/**
 * Interface Emitter
 * @package Prwnr\Streamer\Contracts
 */
interface Emitter
{

    /**
     * @param Event $event
     * @return string
     */
    public function emit(Event $event): string;
}