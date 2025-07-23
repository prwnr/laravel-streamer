<?php

declare(strict_types=1);

namespace Prwnr\Streamer\Exceptions;

use Exception;

/**
 * Used to signal that at least one listener failed
 */
class ListenerFailedException extends Exception
{
} 