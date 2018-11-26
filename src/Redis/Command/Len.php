<?php

namespace Prwnr\Streamer\Redis\Command;

use Predis\Command\Command;

/**
 * @link https://redis.io/commands/xlen
 *
 * Class Len
 * @package Prwnr\Streamer\Redis\Command
 */
class Len extends Command
{
    /**
     * {@inheritdoc}
     */
    public function getId(): string
    {
        return 'XLEN';
    }
}
