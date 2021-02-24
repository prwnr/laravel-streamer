<?php

namespace Prwnr\Streamer\Exceptions;

use Exception;
use Prwnr\Streamer\Errors\FailedMessage;

class MessageRetryFailedException extends Exception
{
    public function __construct(FailedMessage $message, string $additional)
    {
        $errorMessage = sprintf(
            'Failed to retry [%s] on %s stream by [%s] listener. Error: %s',
            $message->getId(),
            $message->getStream()->getName(),
            $message->getReceiver(),
            $additional
        );

        parent::__construct($errorMessage);
    }
}