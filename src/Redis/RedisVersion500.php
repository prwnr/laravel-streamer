<?php

namespace Prwnr\Streamer\Redis;

use Prwnr\Streamer\Redis\Command\{
    Ack, Claim, Del, Group, Info, Len, Pending, Range, Read, Add, ReadGroup, RevRange
};
use Predis\Profile\RedisVersion320;

/**
 * Class RedisVersion500
 * @package Prwnr\Streamer\Redis\Profile
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
            'XACK' => Ack::class,
            'XADD' => Add::class,
            'XCLAIM' => Claim::class,
            'XDEL' => Del::class,
            'XGROUP' => Group::class,
            'XINFO' => Info::class,
            'XLEN' => Len::class,
            'XPENDING' => Pending::class,
            'XRANGE' => Range::class,
            'XREVRANGE' => RevRange::class,
            'XREAD' => Read::class,
            'XREADGROUP' => ReadGroup::class
        ];

        return array_merge($commands320, $commands500);
    }
}