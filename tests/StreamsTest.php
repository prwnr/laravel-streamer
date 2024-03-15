<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\Concerns\InteractsWithRedis;
use Prwnr\Streamer\Streams;

class StreamsTest extends TestCase
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

    public function test_add_message_to_multiple_streams(): void
    {
        $streams = new Streams(['foo', 'bar']);
        $actual = $streams->add($this->makeMessage());

        $this->assertNotEmpty($actual);
        $this->assertCount(2, $actual);
    }

    public function test_read_messages_from_multiple_streams(): void
    {
        $streams = new Streams(['foo', 'bar']);
        $ids = $streams->add($this->makeMessage());

        $actual = $streams->read();

        $this->assertNotEmpty($actual);
        $this->assertCount(2, $actual);
        $this->assertArrayHasKey('foo', $actual);
        $this->assertArrayHasKey('bar', $actual);
        $this->assertCount(1, $actual['foo']);
        $this->assertCount(1, $actual['bar']);
        $this->assertArrayHasKey($ids[0], $actual['foo']);
        $this->assertArrayHasKey($ids[1], $actual['bar']);
    }

    public function test_read_messages_from_multiple_streams_starting_from_specified_id(): void
    {
        $streams = new Streams(['foo', 'bar']);
        $message = $this->makeMessage();
        $ids = [];
        for ($i = 0; $i < 10; $i++) {
            $ids[] = $streams->add($message);
        }

        $secondFoo = $ids[2][0];
        $fifthBar = $ids[5][1];
        $actual = $streams->read([$secondFoo, $fifthBar]);

        $this->assertNotEmpty($actual);
        $this->assertCount(2, $actual);
        $this->assertCount(7, $actual['foo']);
        $this->assertCount(4, $actual['bar']);
    }

    public function test_read_messages_from_multiple_streams_with_limit(): void
    {
        $streams = new Streams(['foo', 'bar']);
        $message = $this->makeMessage();
        for ($i = 0; $i < 10; $i++) {
            $streams->add($message);
        }

        $actual = $streams->read([], 5);

        $this->assertNotEmpty($actual);
        $this->assertCount(2, $actual);
        $this->assertCount(5, $actual['foo']);
        $this->assertCount(5, $actual['bar']);
    }
}
