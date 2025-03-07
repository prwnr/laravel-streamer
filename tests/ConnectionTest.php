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

    public static function getTestCases(): array
    {
        return [
            'test_redis_connection_trait_uses_default_connection' => [
                [
                    'extraAssertions' => static fn (ConnectionTest $test) => static::assertSame('default', $test->connection->getName()),
                ],
            ],
            'test_redis_commands_works_with_custom_redis_connection' => [
                [
                    'setupBeforeRedis' => static fn (ConnectionTest $test) => $test->configureCustomRedisConnection(),
                    'extraAssertions' => static function (ConnectionTest $test) {
                        static::assertSame('custom', $test->connection->getName());
                        static::assertInstanceOf(Redis::class, $test->connection->client());
                        static::assertIsString($test->connection->XADD('foobar', '*', ['key' => 'value']));
                        static::assertEquals(1, $test->connection->XLEN('foobar'));
                        static::assertNotEmpty($test->connection->XREAD(['foobar' => '0']));
                    },
                ],
            ],
        ];
    }

    /**
     * @dataProvider getTestCases
     */
    public function test(array $testCase): void
    {
        if (true === \array_key_exists('setupBeforeRedis', $testCase)) {
            $testCase['setupBeforeRedis']($this);
        }

        $foo = new class () {
            use ConnectsWithRedis;

            public function bar(): Connection
            {
                return $this->redis();
            }
        };

        $this->connection = $foo->bar();
        $testCase['extraAssertions']($this);
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
