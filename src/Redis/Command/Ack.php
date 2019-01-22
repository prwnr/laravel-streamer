<?php

namespace Prwnr\Streamer\Redis\Command;

use Predis\Command\Command;

/**
 * @link https://redis.io/commands/xack
 *
 * Class Ack
 */
class Ack extends Command
{
    /**
     * {@inheritdoc}
     */
    public function getId(): string
    {
        return 'XACK';
    }
}
