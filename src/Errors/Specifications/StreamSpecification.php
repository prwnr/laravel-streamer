<?php

namespace Prwnr\Streamer\Errors\Specifications;

use Prwnr\Streamer\Contracts\Errors\Specification;
use Prwnr\Streamer\Errors\FailedMessage;

class StreamSpecification implements Specification
{
    /**
     * StreamSpecification constructor.
     */
    public function __construct(private readonly string $stream)
    {
    }

    /**
     * @inheritDoc
     */
    public function isSatisfiedBy(FailedMessage $message): bool
    {
        return $message->getStreamInstance()->getName() === $this->stream;
    }
}
