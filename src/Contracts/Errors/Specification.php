<?php

namespace Prwnr\Streamer\Contracts\Errors;

use Prwnr\Streamer\Errors\FailedMessage;

interface Specification
{
    /**
     * @param  FailedMessage  $message
     * @return bool
     */
    public function isSatisfiedBy(FailedMessage $message): bool;
}