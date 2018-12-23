<?php

namespace Prwnr\Streamer;

use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;

/**
 * Trait ConnectsWithRedis
 * @package Prwnr\Streamer
 */
trait ConnectsWithRedis
{

    /**
     * Returns Redis connection based on configuration
     * @return Connection
     */
    protected function redis(): Connection
    {
        $connectionName = config('streamer.redis_connection');
        return Redis::connection($connectionName ?? 'default');
    }
}