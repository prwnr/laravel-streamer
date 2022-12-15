<?php

namespace Prwnr\Streamer\Concerns;

use Illuminate\Redis\Connections\Connection;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Support\Facades\Redis;
use Predis\Client;

/**
 * Trait ConnectsWithRedis.
 */
trait ConnectsWithRedis
{
    /**
     * Returns Redis connection based on configuration.
     *
     * @return Connection|PhpRedisConnection|Client
     */
    protected function redis(): Connection
    {
        $connectionName = config('streamer.redis_connection');

        $connection = Redis::connection($connectionName ?? 'default');
        $connection->setOption(\Redis::OPT_PREFIX, '');

        return $connection;
    }
}
