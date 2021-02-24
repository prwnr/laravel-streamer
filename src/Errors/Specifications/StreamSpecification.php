<?php

namespace Prwnr\Streamer\Errors\Specifications;

use Prwnr\Streamer\Contracts\Errors\Specification;
use Prwnr\Streamer\Errors\FailedMessage;

class StreamSpecification implements Specification
{
    /**
     * @var string
     */
    private $stream;

    /**
     * StreamSpecification constructor.
     *
     * @param  string  $stream
     */
    public function __construct(string $stream)
    {
        $this->stream = $stream;
    }

    /**
     * @inheritDoc
     */
    public function isSatisfiedBy(FailedMessage $message): bool
    {
        return $message->getStream()->getName() === $this->stream;
    }
}