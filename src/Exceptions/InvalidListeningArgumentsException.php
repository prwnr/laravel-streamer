<?php

namespace Prwnr\Streamer\Exceptions;

class InvalidListeningArgumentsException extends \Exception
{
    public function __construct()
    {
        parent::__construct('Not all events have handlers that can process their messages.');
    }
}