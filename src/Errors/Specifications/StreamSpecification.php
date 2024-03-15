<?php

declare(strict_types=1);

namespace Prwnr\Streamer\Errors\Specifications;

use Prwnr\Streamer\Contracts\Errors\Specification;
use Prwnr\Streamer\Errors\FailedMessage;

class StreamSpecification implements Specification
{
    public function __construct(private readonly string $stream)
    {
    }

    public function isSatisfiedBy(FailedMessage $message): bool
    {
        return $message->getStream()->getName() === $this->stream;
    }
}
