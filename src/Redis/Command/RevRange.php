<?php

namespace Prwnr\Streamer\Redis\Command;

use Prwnr\Streamer\Redis\RangeCommand;

/**
 * @link https://redis.io/commands/xrevrange
 *
 * Class RevRange
 * @package Prwnr\Streamer\Redis\Command
 */
class RevRange extends RangeCommand
{
    /**
     * {@inheritdoc}
     */
    public function getId(): string
    {
        return 'XREVRANGE';
    }
}
