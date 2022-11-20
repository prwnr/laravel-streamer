<?php

declare(strict_types=1);

namespace Prwnr\Streamer\Exceptions;

use Exception;

class InvalidListeningArgumentsException extends Exception
{
    public function __construct()
    {
        parent::__construct('Not all events have handlers that can process their messages.');
    }
}
