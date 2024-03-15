<?php

declare(strict_types=1);

namespace Prwnr\Streamer\Errors\Specifications;

use Prwnr\Streamer\Contracts\Errors\Specification;
use Prwnr\Streamer\Errors\FailedMessage;

class IdentifierSpecification implements Specification
{
    public function __construct(private readonly string $id)
    {
    }

    public function isSatisfiedBy(FailedMessage $message): bool
    {
        return $message->getId() === $this->id;
    }
}
