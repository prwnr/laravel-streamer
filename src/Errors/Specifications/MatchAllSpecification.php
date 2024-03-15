<?php

declare(strict_types=1);

namespace Prwnr\Streamer\Errors\Specifications;

use Prwnr\Streamer\Contracts\Errors\Specification;
use Prwnr\Streamer\Errors\FailedMessage;

class MatchAllSpecification implements Specification
{
    /**
     * @var Specification[]
     */
    private readonly array $specifications;

    public function __construct(Specification ...$specifications)
    {
        $this->specifications = $specifications;
    }

    public function isSatisfiedBy(FailedMessage $message): bool
    {
        foreach ($this->specifications as $specification) {
            if (!$specification->isSatisfiedBy($message)) {
                return false;
            }
        }

        return true;
    }
}
