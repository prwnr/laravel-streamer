<?php

namespace Prwnr\Streamer\Errors\Specifications;

use Prwnr\Streamer\Contracts\Errors\Specification;
use Prwnr\Streamer\Errors\FailedMessage;

class ReceiverSpecification implements Specification
{
    /**
     * IdentifierSpecification constructor.
     */
    public function __construct(private readonly string $receiver)
    {
    }

    /**
     * @inheritDoc
     */
    public function isSatisfiedBy(FailedMessage $message): bool
    {
        return $message->receiver === $this->receiver;
    }
}