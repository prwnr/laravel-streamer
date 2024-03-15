<?php

declare(strict_types=1);

namespace Prwnr\Streamer\Contracts\Errors;

use Prwnr\Streamer\Errors\FailedMessage;

interface Specification
{
    public function isSatisfiedBy(FailedMessage $message): bool;
}
