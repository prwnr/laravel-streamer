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

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRedis();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->redis['phpredis']->connection()->flushall();
        $this->tearDownRedis();
    }

    public function test_redis_connection_trait_uses_default_connection(): void
    {
        $foo = new class () {
            use ConnectsWithRedis;

            public function bar(): Connection
            {
                return $this->redis();
            }
        };

        $this->assertInstanceOf(Connection::class, $foo->bar());
        $this->assertInstanceOf(Redis::class, $foo->bar()->client());
        $this->assertEquals('default', $foo->bar()->getName());
    }

    public function test_redis_connection_trait_uses_custom_connection(): void
    {
        $this->configureCustomRedisConnection();

        $foo = new class () {
            use ConnectsWithRedis;

            public function bar(): Connection
            {
                return $this->redis();
            }
        };

        $this->assertInstanceOf(Connection::class, $foo->bar());
        $this->assertInstanceOf(Redis::class, $foo->bar()->client());
        $this->assertEquals('custom', $foo->bar()->getName());
    }

    public function test_redis_commands_works_with_custom_redis_connection(): void
    {
        $this->configureCustomRedisConnection();

        $foo = new class () {
            use ConnectsWithRedis;

            public function bar(): Connection
            {
                return $this->redis();
            }
        };

        $this->assertInstanceOf(Connection::class, $foo->bar());
        $this->assertInstanceOf(Redis::class, $foo->bar()->client());
        $this->assertIsString($foo->bar()->XADD('foobar', '*', ['key' => 'value']));
        $this->assertEquals(1, $foo->bar()->XLEN('foobar'));
        $this->assertNotEmpty($foo->bar()->XREAD(['foobar' => '0']));
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
}
