<?php

declare(strict_types=1);

namespace Prwnr\Streamer\Exceptions;

use Exception;

class ListenerFailedException extends Exception
{
    // Used to signal that at least one listener failed and message should not be acknowledged
} 