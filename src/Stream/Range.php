<?php

namespace Prwnr\Streamer\Stream;

/**
 * Class Range.
 */
class Range
{
    public const FIRST = '-';
    public const LAST = '+';
    public const FORWARD = 1;
    public const BACKWARD = 2;

    private string $start;
    private string $stop;
    private int $direction;

    /**
     * @return string
     */
    public function getStart(): string
    {
        return $this->start;
    }

    /**
     * @return string
     */
    public function getStop(): string
    {
        return $this->stop;
    }

    /**
     * @return int
     */
    public function getDirection(): int
    {
        return $this->direction;
    }

    /**
     * Range constructor.
     *
     * @param string $start
     * @param string $stop
     * @param int    $direction
     */
    public function __construct(string $start = self::FIRST, string $stop = self::LAST, int $direction = self::FORWARD)
    {
        $this->start = $start;
        $this->stop = $stop;
        $this->direction = $direction;
    }
}
