<?php

namespace Prwnr\Streamer\Redis\Command;

use Predis\Command\Command;

/**
 * @link https://redis.io/commands/xdel
 *
 * Class Del
 */
class Del extends Command
{
    /**
     * {@inheritdoc}
     */
    public function getId(): string
    {
        return 'XDEL';
    }
}
