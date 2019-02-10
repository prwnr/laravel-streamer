<?php

namespace Prwnr\Streamer\Redis;

use Predis\Profile\RedisVersion320;
use Prwnr\Streamer\Redis\Command\Ack;
use Prwnr\Streamer\Redis\Command\Add;
use Prwnr\Streamer\Redis\Command\Claim;
use Prwnr\Streamer\Redis\Command\Del;
use Prwnr\Streamer\Redis\Command\Group;
use Prwnr\Streamer\Redis\Command\Info;
use Prwnr\Streamer\Redis\Command\Len;
use Prwnr\Streamer\Redis\Command\Pending;
use Prwnr\Streamer\Redis\Command\Range;
use Prwnr\Streamer\Redis\Command\Read;
use Prwnr\Streamer\Redis\Command\ReadGroup;
use Prwnr\Streamer\Redis\Command\RevRange;

/**
 * Class RedisVersion500.
 */
class RedisVersion500 extends RedisVersion320
{
    /**
     * {@inheritdoc}
     */
    public function getVersion(): string
    {
        return '5.0';
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedCommands(): array
    {
        $commands320 = parent::getSupportedCommands();

        $commands500 = [
            'XACK'       => Ack::class,
            'XADD'       => Add::class,
            'XCLAIM'     => Claim::class,
            'XDEL'       => Del::class,
            'XGROUP'     => Group::class,
            'XINFO'      => Info::class,
            'XLEN'       => Len::class,
            'XPENDING'   => Pending::class,
            'XRANGE'     => Range::class,
            'XREVRANGE'  => RevRange::class,
            'XREAD'      => Read::class,
            'XREADGROUP' => ReadGroup::class,
        ];

        return array_merge($commands320, $commands500);
    }
}
