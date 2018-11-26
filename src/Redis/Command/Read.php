<?php

namespace Prwnr\Streamer\Redis\Command;

use Prwnr\Streamer\Redis\ReadCommand;

/**
 * @link https://redis.io/commands/xread
 *
 * Class Read
 * @package Prwnr\Streamer\Redis\Command
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
