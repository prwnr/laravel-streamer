<?php

namespace Prwnr\Streamer\Stream;

class Range
{
    public const FIRST = '-';

    public const LAST = '+';

    public const FORWARD = 1;

    public const BACKWARD = 2;

    public function __construct(
        private readonly string $start = self::FIRST,
        private readonly string $stop = self::LAST,
        private readonly int $direction = self::FORWARD
    ) {
    }

    public function getStart(): string
    {
        return $this->start;
    }

    public function getStop(): string
    {
        return $this->stop;
    }

    public function getDirection(): int
    {
        return $this->direction;
    }
}
