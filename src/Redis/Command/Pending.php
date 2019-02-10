<?php

namespace Prwnr\Streamer\Redis\Command;

use Predis\Command\Command;

/**
 * @link https://redis.io/commands/xpending
 *
 * Class Pending
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
