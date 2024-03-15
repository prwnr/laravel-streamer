<?php

declare(strict_types=1);

namespace Prwnr\Streamer\Contracts\Errors;

use Exception;
use Prwnr\Streamer\Contracts\MessageReceiver;
use Prwnr\Streamer\Errors\FailedMessage;
use Prwnr\Streamer\EventDispatcher\ReceivedMessage;
use Prwnr\Streamer\Exceptions\MessageRetryFailedException;

interface MessagesFailer
{
    /**
     * Stores failed message information in a list for later retry attempt.
     */
    public function store(ReceivedMessage $message, MessageReceiver $receiver, Exception $e): void;

    /**
     * Looks up message on a stream and attempts to retry it with given receiver.
     *
     * @throws MessageRetryFailedException
     */
    public function retry(FailedMessage $message): void;
}
