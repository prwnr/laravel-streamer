<?php

namespace Prwnr\Streamer\Redis\Command;

use Predis\Command\Command;

/**
 * @link https://redis.io/commands/xpending
 *
 * Class Pending
 * @package Prwnr\Streamer\Redis\Command
 */
class Pending extends Command
{
    /**
     * {@inheritdoc}
     */
    public function getId(): string
    {
        return 'XPENDING';
    }
}
