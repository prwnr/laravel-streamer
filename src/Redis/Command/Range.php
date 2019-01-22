<?php

namespace Prwnr\Streamer\Redis\Command;

use Prwnr\Streamer\Redis\RangeCommand;

/**
 * @link https://redis.io/commands/xrange
 *
 * Class Range
 */
class Range extends RangeCommand
{
    /**
     * {@inheritdoc}
     */
    public function getId(): string
    {
        return 'XRANGE';
    }
}
