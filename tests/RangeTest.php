<?php

declare(strict_types=1);

namespace Tests;

use Prwnr\Streamer\Stream\Range;

class RangeTest extends TestCase
{
    public function test_range_returns_start_and_stop(): void
    {
        $defaultRange = new Range();
        $customRange = new Range('0', '100');

        $this->assertEquals('-', $defaultRange->start);
        $this->assertEquals('+', $defaultRange->stop);

        $this->assertEquals('0', $customRange->start);
        $this->assertEquals('100', $customRange->stop);
    }

    public function test_range_returns_direction(): void
    {
        $forwardRange = new Range();
        $backwardRange = new Range(Range::FIRST, Range::LAST, Range::BACKWARD);

        $this->assertEquals(Range::FORWARD, $forwardRange->direction);
        $this->assertEquals(Range::BACKWARD, $backwardRange->direction);
    }
}
