<?php

namespace Tests;

use Illuminate\Foundation\Testing\Concerns\InteractsWithRedis;
use Illuminate\Redis\Connections\Connection;
use Predis\Client;
use Prwnr\Streamer\ConnectsWithRedis;
use Prwnr\Streamer\Stream;

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
        $this->redis['predis']->connection()->flushall();
        $this->tearDownRedis();
    }

    public function test_redis_connection_trait_uses_default_connection(): void
    {
        $foo = new class {
            use ConnectsWithRedis;

            public function bar()
            {
                return $this->redis();
            }
        };

        $this->assertInstanceOf(Connection::class, $foo->bar());
        $this->assertInstanceOf(Client::class, $foo->bar()->client());
        $this->assertEquals('default', $foo->bar()->getName());
    }

    public function test_redis_connection_trait_uses_custom_connection(): void
    {
        $this->configureCustomRedisConnection();

        $foo = new class {
            use ConnectsWithRedis;

            public function bar()
            {
                return $this->redis();
            }
        };

        $this->assertInstanceOf(Connection::class, $foo->bar());
        $this->assertInstanceOf(Client::class, $foo->bar()->client());
        $this->assertEquals('custom', $foo->bar()->getName());
    }

    public function test_redis_commands_works_with_custom_redis_connection(): void
    {
        $this->configureCustomRedisConnection();

        $foo = new class {
            use ConnectsWithRedis;

            public function bar()
            {
                return $this->redis();
            }
        };

        $this->assertInstanceOf(Connection::class, $foo->bar());
        $this->assertInstanceOf(Client::class, $foo->bar()->client());
        $this->assertInternalType('string', $foo->bar()->XADD('foobar', '*', 'key', 'value'));
        $this->assertEquals(1, $foo->bar()->XLEN('foobar'));
        $this->assertNotEmpty($foo->bar()->XREAD(Stream::COUNT, 0, Stream::STREAMS, 'foobar', 0));
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