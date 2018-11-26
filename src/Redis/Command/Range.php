<?php

namespace Prwnr\Streamer\Redis\Command;

use Prwnr\Streamer\Redis\RangeCommand;

/**
 * @link https://redis.io/commands/xrange
 *
 * Class Range
 * @package Prwnr\Streamer\Redis\Command
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
