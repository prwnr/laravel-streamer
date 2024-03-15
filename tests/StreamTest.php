<?php

declare(strict_types=1);

namespace Tests;

use BadMethodCallException;
use Illuminate\Foundation\Testing\Concerns\InteractsWithRedis;
use Prwnr\Streamer\Stream;
use Prwnr\Streamer\Stream\Range;
use Prwnr\Streamer\StreamNotFoundException;

class StreamTest extends TestCase
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

    public function test_return_stream_name(): void
    {
        $stream = new Stream('foo');
        $this->assertEquals('foo', $stream->getName());
    }

    public function test_return_stream_new_entries_key(): void
    {
        $stream = new Stream('foo');
        $this->assertEquals('$', $stream->getNewEntriesKey());
    }

    public function test_add_message_to_stream(): void
    {
        $stream = new Stream('foo');

        $actual = $stream->add($this->makeMessage());

        $this->assertNotNull($actual);
        $this->assertIsString($actual);
    }

    public function test_add_message_to_stream_with_fixed_id(): void
    {
        $stream = new Stream('foo');
        $expected = '1-0';

        $actual = $stream->add($this->makeMessage(), $expected);

        $this->assertNotNull($actual);
        $this->assertIsString($actual);
        $this->assertEquals($expected, $actual);
    }

    public function test_read_message_from_stream(): void
    {
        $stream = new Stream('foo');
        $id = $stream->add($this->makeMessage());

        $actual = $stream->read();

        $this->assertNotEmpty($actual);
        $this->assertCount(1, $actual);
        $this->assertArrayHasKey($stream->getName(), $actual);
        $this->assertEquals([$id => ['foo' => 'bar']], $actual[$stream->getName()]);
    }

    public function test_read_messages_starting_with_specified_id_from_stream(): void
    {
        $stream = new Stream('foo');
        $message = $this->makeMessage();

        $ids = [];
        for ($i = 0; $i < 3; $i++) {
            $ids[] = $stream->add($message);
        }

        $actual = $stream->read($ids[1]);

        $this->assertNotEmpty($actual);
        $this->assertArrayHasKey($stream->getName(), $actual);
        $this->assertCount(1, $actual[$stream->getName()]);
        $this->assertArrayNotHasKey($ids[0], $actual[$stream->getName()]);
    }

    public function test_read_limits_messages_number_from_stream(): void
    {
        $stream = new Stream('foo');
        $message = $this->makeMessage();

        for ($i = 0; $i < 10; $i++) {
            $stream->add($message);
        }
        $actual = $stream->read(Stream::FROM_START, 5);

        $this->assertNotEmpty($actual);
        $this->assertArrayHasKey($stream->getName(), $actual);
        $this->assertCount(5, $actual[$stream->getName()]);
    }

    public function test_delete_message_from_stream(): void
    {
        $stream = new Stream('foo');
        $id = $stream->add($this->makeMessage());

        $actual = $stream->delete($id);

        $this->assertEquals('1', $actual);
        $this->assertEmpty($stream->read());
    }

    public function test_await_returns_new_message_from_stream(): void
    {
        $stream = new Stream('foo');
        $id = $stream->add($this->makeMessage());

        $actual = $stream->await(Stream::FROM_START, 1);

        $this->assertNotEmpty($actual);
        $this->assertCount(1, $actual);
        $this->assertArrayHasKey($stream->getName(), $actual);
        $this->assertCount(1, $actual[$stream->getName()]);
        $this->assertEquals([$id => ['foo' => 'bar']], $actual[$stream->getName()]);
    }

    public function test_await_returns_nothing_with_timeout_when_stream_has_no_messages(): void
    {
        $stream = new Stream('foo');

        $actual = $stream->await(Stream::FROM_START, 1);

        $this->assertEmpty($actual);
    }

    public function test_read_range_of_messages_from_stream_from_first_to_last(): void
    {
        $stream = new Stream('foo');
        $message = $this->makeMessage();

        $expected = [];
        for ($i = 0; $i < 10; $i++) {
            $expected[] = $stream->add($message);
        }

        $range = new Range();
        $actual = $stream->readRange($range);
        $keys = array_keys($actual);

        $this->assertNotEmpty($actual);
        $this->assertCount(10, $actual);
        $this->assertEquals($expected[0], $keys[0]);
        $this->assertEquals($expected[9], $keys[9]);
    }

    public function test_read_range_of_messages_from_stream_with_limit(): void
    {
        $stream = new Stream('foo');
        $message = $this->makeMessage();

        $expected = [];
        for ($i = 0; $i < 10; $i++) {
            $expected[] = $stream->add($message);
        }

        $range = new Range();
        $actual = $stream->readRange($range, 5);
        $keys = array_keys($actual);

        $this->assertNotEmpty($actual);
        $this->assertCount(5, $actual);
        $this->assertEquals($expected[0], $keys[0]);
        $this->assertEquals($expected[4], $keys[4]);
    }

    public function test_read_range_of_messages_from_stream_from_last_to_first(): void
    {
        $stream = new Stream('foo');
        $message = $this->makeMessage();

        $expected = [];
        for ($i = 0; $i < 10; $i++) {
            $expected[] = $stream->add($message);
        }

        $range = new Range(Range::FIRST, Range::LAST, Range::BACKWARD);
        $actual = $stream->readRange($range);
        $keys = array_keys($actual);

        $this->assertNotEmpty($actual);
        $this->assertCount(10, $actual);
        $this->assertEquals($expected[9], $keys[0]);
        $this->assertEquals($expected[0], $keys[9]);
    }

    public function test_create_new_group_and_stream_currently_not_existing(): void
    {
        $stream = new Stream('foo');

        $stream->createGroup('bar');

        $this->assertTrue($stream->groupExists('bar'));
    }

    public function test_create_new_group_for_existing_stream(): void
    {
        $stream = new Stream('foo');
        $stream->add($this->makeMessage());

        $result = $stream->createGroup('bar', Stream::FROM_START, false);

        $this->assertTrue($result);
        $this->assertTrue($stream->groupExists('bar'));
    }

    public function test_create_new_group_fails_when_stream_does_not_exists_and_is_not_created_with_group(): void
    {
        $stream = new Stream('foo');

        $result = $stream->createGroup('bar', Stream::FROM_START, false);
        $this->assertFalse($result);
    }

    public function test_pending_messages_returned_from_stream_for_group(): void
    {
        $stream = new Stream('foo');
        $stream->createGroup('bar');
        $consumer = new Stream\Consumer('foobar', $stream, 'bar');
        $id = $stream->add($this->makeMessage());
        $stream->add($this->makeMessage());
        //Consume message without acknowledging it, so it stays as pending
        $consumer->await($consumer->getNewEntriesKey());

        $actual = $stream->pending('bar');
        $message = $actual[0];

        $this->assertNotEmpty($actual);
        $this->assertCount(2, $actual);
        $this->assertCount(4, $message);
        $this->assertEquals($id, $message[0]);
        $this->assertEquals('foobar', $message[1]);
        $this->assertIsInt($message[2]);
        $this->assertEquals(1, $message[3]);
    }

    public function test_pending_messages_returned_from_stream_for_group_consumer(): void
    {
        $stream = new Stream('foo');
        $stream->createGroup('bar');
        $consumer = new Stream\Consumer('foobar', $stream, 'bar');
        $id = $stream->add($this->makeMessage());
        //Consume message without acknowledging it, so it stays as pending
        $consumer->await($consumer->getNewEntriesKey());

        $actual = $stream->pending('bar', 'foobar');
        $message = $actual[0];

        $this->assertNotEmpty($actual);
        $this->assertCount(1, $actual);
        $this->assertCount(4, $message);
        $this->assertEquals($id, $message[0]);
        $this->assertEquals('foobar', $message[1]);
        $this->assertIsInt($message[2]);
        $this->assertEquals(1, $message[3]);
    }

    public function test_len_returns_number_of_messages_on_stream(): void
    {
        $streamA = new Stream('fooA');
        $streamB = new Stream('fooB');
        $streamB->add($this->makeMessage());

        $this->assertEquals(0, $streamA->len());
        $this->assertEquals(1, $streamB->len());
    }

    public function test_info_returned_for_stream(): void
    {
        $stream = new Stream('foo');
        //populate stream with messages and group
        $stream->createGroup('bar');
        $message = $this->makeMessage();
        $messages = [];
        for ($i = 0; $i < 10; $i++) {
            $messages[] = $stream->add($message);
        }

        $actual = $stream->info();

        $this->assertNotEmpty($actual);
        $this->assertEquals([
            'length',
            'radix-tree-keys',
            'radix-tree-nodes',
            'last-generated-id',
            'max-deleted-entry-id',
            'entries-added',
            'recorded-first-entry-id',
            'groups',
            'first-entry',
            'last-entry',
        ], array_keys($actual));
        $this->assertEquals(10, $actual['length']);
        $this->assertEquals(1, $actual['groups']);
        $this->assertEquals($messages[9], $actual['last-generated-id']);
        $this->assertArrayHasKey($messages[0], $actual['first-entry']);
        $this->assertArrayHasKey($messages[9], $actual['last-entry']);
    }

    public function test_full_info_returned_for_stream(): void
    {
        $stream = new Stream('foo');

        $info = $this->redis['phpredis']->connection()->info();
        if (!version_compare($info['redis_version'], '6.0.0', '>=')) {
            $this->expectException(BadMethodCallException::class);
            $this->expectExceptionMessage('fullInfo only available for Redis 6.0 or above.');

            $stream->fullInfo();
            return;
        }

        //populate stream with messages and group
        $stream->createGroup('bar');
        $message = $this->makeMessage();
        $messages = [];
        for ($i = 0; $i < 10; $i++) {
            $messages[] = $stream->add($message);
        }

        $actual = $stream->fullInfo();

        $this->assertNotEmpty($actual);
        $this->assertEquals([
            'length',
            'radix-tree-keys',
            'radix-tree-nodes',
            'last-generated-id',
            'max-deleted-entry-id',
            'entries-added',
            'recorded-first-entry-id',
            'entries',
            'groups',
        ], array_keys($actual));
        $this->assertEquals(10, $actual['length']);
        $this->assertCount(1, $actual['groups']);
        $this->assertCount(10, $actual['entries']);
        $this->assertEquals($messages[9], $actual['last-generated-id']);

        $this->assertNotEquals($actual, $stream->info());
    }

    public function test_info_throws_stream_not_found_exception_when_stream_does_not_exists(): void
    {
        $stream = new Stream('foo');

        $this->expectException(StreamNotFoundException::class);
        $stream->info();
    }

    public function test_groups_returned_for_stream(): void
    {
        $stream = new Stream('foo');
        $expected = ['bar1', 'bar2'];
        foreach ($expected as $value) {
            $stream->createGroup($value);
        }

        $actual = $stream->groups();

        $this->assertCount(2, $actual);
        foreach (['name', 'consumers', 'pending', 'last-delivered-id'] as $key) {
            $this->assertArrayHasKey($key, $actual[0]);
        }
        $this->assertEquals($expected[0], $actual[0]['name']);
        $this->assertEquals($expected[1], $actual[1]['name']);
    }

    public function test_groups_throws_stream_not_found_exception_when_stream_does_not_exists(): void
    {
        $stream = new Stream('foo');

        $this->expectException(StreamNotFoundException::class);
        $stream->groups();
    }

    public function test_consumers_returned_for_stream(): void
    {
        $stream = new Stream('foo');
        $stream->createGroup('bar');
        $stream->add($this->makeMessage());
        $consumer = new Stream\Consumer('foobar', $stream, 'bar');
        //Awaiting as consumer creates a consumer on a Stream
        $consumer->await($consumer->getNewEntriesKey(), 1);

        $actual = $stream->consumers('bar');

        $this->assertNotEmpty($actual);
        $this->assertCount(1, $actual);
        foreach (['name', 'pending', 'idle'] as $key) {
            $this->assertArrayHasKey($key, $actual[0]);
        }
        $this->assertEquals(1, $actual[0]['pending']);
    }

    public function test_consumers_throws_stream_not_found_exception_when_stream_does_not_exists(): void
    {
        $stream = new Stream('foo');

        $this->expectException(StreamNotFoundException::class);
        $stream->consumers('bar');
    }

    public function test_check_if_group_exists(): void
    {
        $stream = new Stream('foo');
        $stream->createGroup('bar');

        $this->assertTrue($stream->groupExists('bar'));
        $this->assertFalse($stream->groupExists('foobar'));
    }

    public function test_check_if_group_exists_when_stream_does_not_exists(): void
    {
        $stream = new Stream('foo');
        $this->assertFalse($stream->groupExists('foobar'));
    }
}
