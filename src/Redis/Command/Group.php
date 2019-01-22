<?php

namespace Prwnr\Streamer\Redis\Command;

use Predis\Command\Command;

/**
 * @link https://redis.io/commands/xgroup
 *
 * Class Group
 */
class Group extends Command
{
    /**
     * {@inheritdoc}
     */
    public function getId(): string
    {
        return 'XGROUP';
    }
}
