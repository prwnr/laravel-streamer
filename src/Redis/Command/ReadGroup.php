<?php

namespace Prwnr\Streamer\Redis\Command;

use Prwnr\Streamer\Redis\ReadCommand;

/**
 * @link https://redis.io/commands/xreadgroup
 *
 * Class ReadGroup
 * @package Prwnr\Streamer\Redis\Command
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
