<?php

namespace Prwnr\Streamer\Redis\Command;

use Prwnr\Streamer\Redis\ReadCommand;

/**
 * @link https://redis.io/commands/xread
 *
 * Class Read
 */
class Read extends ReadCommand
{
    /**
     * {@inheritdoc}
     */
    public function getId(): string
    {
        return 'XREAD';
    }
}
