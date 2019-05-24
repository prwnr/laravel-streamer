<?php

namespace Prwnr\Streamer\Redis\Command;

use Predis\Command\Command;
use Prwnr\Streamer\Concerns\Mergeable;

/**
 * @link https://redis.io/commands/xinfo
 *
 * Class Info
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
