<?php

namespace Prwnr\Streamer\Redis\Command;

use Prwnr\Streamer\Redis\Mergeable;
use Predis\Command\Command;

/**
 * @link https://redis.io/commands/xinfo
 *
 * Class Info
 * @package Prwnr\Streamer\Redis\Command
 */
class Info extends Command
{
    use Mergeable;

    /**
     * {@inheritdoc}
     */
    public function getId(): string
    {
        return 'XINFO';
    }

    /**
     * {@inheritdoc}
     */
    public function parseResponse($data)
    {
        $this->dimensions = 3;
        return $this->toAssoc($data);
    }
}
