<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\Concerns\InteractsWithRedis;
use Illuminate\Redis\Connections\Connection;
use Prwnr\Streamer\Concerns\ConnectsWithRedis;
use Redis;

class ConnectionTest extends TestCase
{
    use InteractsWithRedis;

    private Connection $connection;

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->connection->flushall();
        $this->tearDownRedis();
    }

    public function test_redis_connection_trait_uses_default_connection()
    {
        $this->createRedisConnection();

        static::assertInstanceOf(Redis::class, $this->connection->client());
        static::assertSame('default', $this->connection->getName());
    }

    public function test_redis_commands_works_with_custom_redis_connection()
    {
        $this->configureCustomRedisConnection();
        $this->createRedisConnection();

        static::assertSame('custom', $this->connection->getName());
        static::assertInstanceOf(Redis::class, $this->connection->client());
        static::assertIsString($this->connection->XADD('foobar', '*', ['key' => 'value']));
        static::assertEquals(1, $this->connection->XLEN('foobar'));
        static::assertNotEmpty($this->connection->XREAD(['foobar' => '0']));
    }

    private function configureCustomRedisConnection(): void
    {
        $this->app['config']->set('database.redis.custom', [
            'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
            'port' => getenv('REDIS_PORT') ?: 6379,
            'database' => 1,
            'timeout' => 0.5,
        ]);

        $this->app['config']->set('streamer.redis_connection', 'custom');
    }

    private function createRedisConnection(): void
    {
        $foo = new class () {
            use ConnectsWithRedis;

            public function bar(): Connection
            {
                return $this->redis();
            }
        };

        $this->connection = $foo->bar();
    }
}
