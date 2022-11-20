<?php

declare(strict_types=1);

namespace Prwnr\Streamer\Stream;

/**
 * Class Range.
 */
class Range
{
    final public const FIRST = '-';
    final public const LAST = '+';
    final public const FORWARD = 1;
    final public const BACKWARD = 2;

    public function __construct(
        public readonly string $start = self::FIRST,
        public readonly string $stop = self::LAST,
        public readonly int $direction = self::FORWARD
    ) {
    }
}
