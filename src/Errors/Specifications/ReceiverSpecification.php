<?php

namespace Prwnr\Streamer\Errors\Specifications;

use Prwnr\Streamer\Contracts\Errors\Specification;
use Prwnr\Streamer\Errors\FailedMessage;

class ReceiverSpecification implements Specification
{
    /**
     * @var string
     */
    private $receiver;

    /**
     * IdentifierSpecification constructor.
     *
     * @param  string  $receiver
     */
    public function __construct(string $receiver)
    {
        $this->receiver = $receiver;
    }

    /**
     * @inheritDoc
     */
    public function isSatisfiedBy(FailedMessage $message): bool
    {
        return $message->getReceiver() === $this->receiver;
    }
}