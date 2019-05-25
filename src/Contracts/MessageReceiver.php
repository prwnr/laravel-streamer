<?php

namespace Prwnr\Streamer\Contracts;

use Prwnr\Streamer\EventDispatcher\ReceivedMessage;

/**
 * Interface MessageReceiver
 */
interface MessageReceiver
{

    /**
     * @param  ReceivedMessage  $message
     */
    public function handle(ReceivedMessage $message): void;
}