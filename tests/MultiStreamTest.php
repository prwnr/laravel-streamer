<?php

declare(strict_types=1);

namespace Tests;

use Exception;
use Illuminate\Foundation\Testing\Concerns\InteractsWithRedis;
use Prwnr\Streamer\Stream;
use Prwnr\Streamer\Stream\MultiStream;

class MultiStreamTest extends TestCase
{
    use InteractsWithRedis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRedis();
        $this->redis['phpredis']->connection()->flushall();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->redis['phpredis']->connection()->flushall();
        $this->tearDownRedis();
    }

    public function test_new_multi_streams(): void
    {
        $multi = new MultiStream(['foo', 'bar']);

        $this->assertCount(2, $multi->streams());
        $streams = $multi->streams();
        $this->assertArrayHasKey('foo', $streams);
        $this->assertArrayHasKey('bar', $streams);
    }

    public function test_new_multi_streams_with_consumer_and_group(): void
    {
        $multi = new MultiStream(['foo', 'bar'], 'group', 'consumer');

        $this->assertCount(2, $multi->streams());
        $streams = $multi->streams();
        $this->assertArrayHasKey('foo', $streams);
        $this->assertArrayHasKey('bar', $streams);

        $this->assertTrue($streams->get('foo')->groupExists('group'));
        $this->assertTrue($streams->get('bar')->groupExists('group'));
    }

    public function test_add_new_message_to_one_of_the_streams(): void
    {
        $multi = new MultiStream(['foo', 'bar']);

        $ids = $multi->add(['foo' => '1-0', 'bar' => '1-0'], $this->makeMessage());
        $fooBarIds = $multi->add(['foobar' => '1-0'], $this->makeMessage());

        $this->assertNotEmpty($ids);
        $this->assertCount(2, $ids);
        $this->assertEquals([
            'foo' => '1-0',
            'bar' => '1-0',
        ], $ids);

        $this->assertEmpty($fooBarIds);
    }

    public function test_deletes_message_from_one_of_the_streams(): void
    {
        $multi = new MultiStream(['foo', 'bar']);
        $foobar = new Stream('foobar');

        $ids = $multi->add(['foo' => null, 'bar' => null], $this->makeMessage());
        $foobarId = $foobar->add($this->makeMessage());

        $deleted = $multi->delete(['foo' => [$ids['foo']], 'bar' => [$ids['bar']]]);
        $foobarDeleted = $multi->delete(['foobar', [$foobarId]]);

        $this->assertEquals(2, $deleted);
        $this->assertEquals(0, $foobarDeleted);
    }

    public function test_awaits_messages_from_single_stream(): void
    {
        $multi = new MultiStream(['foo']);

        $multi->add(['foo' => '1-0'], $this->makeMessage());
        $multi->add(['foo' => '2-0'], $this->makeMessage());

        $result = $multi->await('0-0');

        $this->assertNotEmpty($result);
        $this->assertCount(2, $result);
        $this->assertEquals([
            [
                'stream' => 'foo',
                'id' => '1-0',
                'message' => ['foo' => 'bar'],
            ],
            [
                'stream' => 'foo',
                'id' => '2-0',
                'message' => ['foo' => 'bar'],
            ],
        ], $result);

        $result = $multi->await('3-0', 1);
        $this->assertEmpty($result);
    }

    public function test_awaits_messages_from_multiple_streams(): void
    {
        $multi = new MultiStream(['foo', 'bar']);

        $multi->add(['foo' => '1-0', 'bar' => '1-0'], $this->makeMessage());
        $multi->add(['foo' => '2-0', 'bar' => '2-0'], $this->makeMessage());

        $result = $multi->await('0-0');

        $this->assertNotEmpty($result);
        $this->assertCount(4, $result);
        $this->assertEquals([
            [
                'stream' => 'foo',
                'id' => '1-0',
                'message' => ['foo' => 'bar'],
            ],
            [
                'stream' => 'bar',
                'id' => '1-0',
                'message' => ['foo' => 'bar'],
            ],
            [
                'stream' => 'foo',
                'id' => '2-0',
                'message' => ['foo' => 'bar'],
            ],
            [
                'stream' => 'bar',
                'id' => '2-0',
                'message' => ['foo' => 'bar'],
            ],
        ], $result);
    }

    public function test_awaits_single_stream_consumer_new_messages(): void
    {
        $multi = new MultiStream(['foo'], 'group', 'consumer');

        $multi->add(['foo' => '1-0'], $this->makeMessage());
        $multi->add(['foo' => '2-0'], $this->makeMessage());

        $result = $multi->await();

        $this->assertNotEmpty($result);
        $this->assertCount(2, $result);
        $this->assertEquals([
            [
                'stream' => 'foo',
                'id' => '1-0',
                'message' => ['foo' => 'bar'],
            ],
            [
                'stream' => 'foo',
                'id' => '2-0',
                'message' => ['foo' => 'bar'],
            ],
        ], $result);
    }

    public function test_awaits_multiple_stream_consumer_new_messages(): void
    {
        $multi = new MultiStream(['foo', 'bar'], 'group', 'consumer');

        $multi->add(['foo' => '1-0', 'bar' => '1-0'], $this->makeMessage());
        $multi->add(['foo' => '2-0', 'bar' => '2-0'], $this->makeMessage());

        $result = $multi->await();

        $this->assertNotEmpty($result);
        $this->assertCount(4, $result);
        $this->assertEquals([
            [
                'stream' => 'foo',
                'id' => '1-0',
                'message' => ['foo' => 'bar'],
            ],
            [
                'stream' => 'bar',
                'id' => '1-0',
                'message' => ['foo' => 'bar'],
            ],
            [
                'stream' => 'foo',
                'id' => '2-0',
                'message' => ['foo' => 'bar'],
            ],
            [
                'stream' => 'bar',
                'id' => '2-0',
                'message' => ['foo' => 'bar'],
            ],
        ], $result);
    }

    public function test_acknowledges_messages_for_consumer(): void
    {
        $multi = new MultiStream(['foo', 'bar'], 'group', 'consumer');

        $multi->add(['foo' => '1-0', 'bar' => '1-0'], $this->makeMessage());
        $multi->add(['foo' => '2-0', 'bar' => '2-0'], $this->makeMessage());

        $result = $multi->await();

        $this->assertNotEmpty($result);
        $this->assertCount(4, $result);

        $multi->acknowledge(['foo' => ['1-0'], ['bar' => ['1-0', '2-0']]]);

        $consumer = new Stream\Consumer('consumer', $multi->streams()->get('foo'), 'group');

        $this->assertCount(1, $consumer->pending());
    }

    public function test_not_all_streams_are_acknowledged(): void
    {
        $multi = new MultiStream(['foo', 'bar'], 'group', 'consumer');

        $multi->add(['foo' => '1-0', 'bar' => '3-0'], $this->makeMessage());

        $result = $multi->await();

        $this->assertNotEmpty($result);
        $this->assertCount(2, $result);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Not all messages were acknowledged. Streams affected: bar');

        $multi->acknowledge(['foo' => ['1-0'], 'bar' => ['1-0', '2-0']]);

        $consumer = new Stream\Consumer('consumer', $multi->streams()->get('foo'), 'group');

        $this->assertCount(0, $consumer->pending());
    }
}
