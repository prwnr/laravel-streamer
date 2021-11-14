<?php

namespace Prwnr\Streamer\Errors\Specifications;

use Prwnr\Streamer\Contracts\Errors\Specification;
use Prwnr\Streamer\Errors\FailedMessage;

class IdentifierSpecification implements Specification
{
    private string $id;

    /**
     * IdentifierSpecification constructor.
     *
     * @param  string  $id
     */
    public function __construct(string $id)
    {
        $this->id = $id;
    }

    /**
     * @inheritDoc
     */
    public function isSatisfiedBy(FailedMessage $message): bool
    {
        return $message->getId() === $this->id;
    }
}