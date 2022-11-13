<?php

namespace Prwnr\Streamer\Errors\Specifications;

use Prwnr\Streamer\Contracts\Errors\Specification;
use Prwnr\Streamer\Errors\FailedMessage;

class IdentifierSpecification implements Specification
{
    /**
     * IdentifierSpecification constructor.
     */
    public function __construct(private readonly string $id)
    {
    }

    /**
     * @inheritDoc
     */
    public function isSatisfiedBy(FailedMessage $message): bool
    {
        return $message->id === $this->id;
    }
}
