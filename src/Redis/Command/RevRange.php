<?php

namespace Prwnr\Streamer\Redis\Command;

use Prwnr\Streamer\Redis\RangeCommand;

/**
 * @link https://redis.io/commands/xrevrange
 *
 * Class RevRange
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
