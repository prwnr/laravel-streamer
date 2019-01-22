<?php

namespace Prwnr\Streamer\Redis\Command;

use Prwnr\Streamer\Redis\ReadCommand;

/**
 * @link https://redis.io/commands/xreadgroup
 *
 * Class ReadGroup
 */
class ReadGroup extends ReadCommand
{
    /**
     * {@inheritdoc}
     */
    public function getId(): string
    {
        return 'XREADGROUP';
    }
}
