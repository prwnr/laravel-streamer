<?php

declare(strict_types=1);

namespace Tests;

use Exception;
use Illuminate\Foundation\Testing\Concerns\InteractsWithRedis;
use Prwnr\Streamer\Stream;

class ConsumerTest extends TestCase
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

    public function test_return_stream_and_new_entries_key_for_consumer(): void
    {
        $stream = new Stream('foo');
        $consumer = new Stream\Consumer('foobar', $stream, 'bar');

        $this->assertEquals($stream->getName(), $consumer->getName());
        $this->assertEquals('>', $consumer->getNewEntriesKey());
    }

    public function test_await_get_message_from_stream_for_consumer(): void
    {
        $stream = new Stream('foo');
        $stream->createGroup('bar');
        $consumer = new Stream\Consumer('foobar', $stream, 'bar');

        $id = $stream->add($this->makeMessage());

        $actual = $consumer->await();

        $this->assertNotEmpty($actual);
        $this->assertCount(1, $actual);
        $this->assertArrayHasKey('foo', $actual);
        $this->assertCount(1, $actual['foo']);
        $this->assertArrayHasKey($id, $actual['foo']);
        $this->assertEquals(['foo' => 'bar'], $actual['foo'][$id]);
    }

    public function test_await_returns_nothing_with_timeout_when_stream_has_no_messages_for_consumer(): void
    {
        $stream = new Stream('foo');
        $stream->createGroup('bar');
        $consumer = new Stream\Consumer('foobar', $stream, 'bar');

        $actual = $consumer->await(Stream\Consumer::NEW_ENTRIES, 1);

        $this->assertEmpty($actual);
    }

    public function test_pending_messages_returned_from_stream_for_consumer(): void
    {
        $stream = new Stream('foo');
        $stream->createGroup('bar');
        $consumer = new Stream\Consumer('foobar', $stream, 'bar');
        $id = $stream->add($this->makeMessage());
        $stream->add($this->makeMessage());
        //Consume message without acknowledging it, so it stays as pending
        $consumer->await($consumer->getNewEntriesKey());

        $actual = $consumer->pending();
        $message = $actual[0];

        $this->assertNotEmpty($actual);
        $this->assertCount(2, $actual);
        $this->assertCount(4, $message);
        $this->assertEquals($id, $message[0]);
        $this->assertEquals('foobar', $message[1]);
        $this->assertIsInt($message[2]);
        $this->assertEquals(1, $message[3]);
    }

    public function test_acknowledging_message_removes_it_from_pending_list(): void
    {
        $stream = new Stream('foo');
        $stream->createGroup('bar');
        $consumer = new Stream\Consumer('foobar', $stream, 'bar');
        $id = $stream->add($this->makeMessage());
        //Consume message without acknowledging it, so it stays as pending
        $consumer->await($consumer->getNewEntriesKey());
        $consumer->acknowledge($id);

        $actual = $consumer->pending();

        $this->assertEmpty($actual);
    }

    public function test_acknowledging_not_existing_message_throws_exception(): void
    {
        $stream = new Stream('foo');
        $stream->createGroup('bar');
        $consumer = new Stream\Consumer('foobar', $stream, 'bar');

        $this->expectException(Exception::class);
        $consumer->acknowledge('0-0');
    }

    public function test_claim_assigns_messages_for_consumer_from_other_consumer(): void
    {
        $stream = new Stream('foo');
        $stream->createGroup('bar');
        $consumerA = new Stream\Consumer('foobar', $stream, 'bar');
        $consumerB = new Stream\Consumer('barfoo', $stream, 'bar');

        $ids = [];
        $ids[] = $stream->add($this->makeMessage());
        $ids[] = $stream->add($this->makeMessage());

        //Consume message without acknowledging it, so it stays as pending
        $consumerA->await($consumerA->getNewEntriesKey());
        sleep(1); // sleep for a second to get idle time on messages

        $this->assertNotEmpty($consumerA->pending());
        $this->assertEmpty($consumerB->pending());
        $this->assertCount(2, $consumerA->pending());

        $this->assertEquals($ids, $consumerB->claim($ids, 1));
        $this->assertNotEmpty($consumerB->pending());
        $this->assertEmpty($consumerA->pending());
        $this->assertCount(2, $consumerB->pending());
    }

    public function test_claim_returns_messages_content_not_just_ids(): void
    {
        $stream = new Stream('foo');
        $stream->createGroup('bar');
        $consumerA = new Stream\Consumer('foobar', $stream, 'bar');
        $consumerB = new Stream\Consumer('barfoo', $stream, 'bar');

        $ids = [];
        $ids[] = $stream->add($this->makeMessage());
        $ids[] = $stream->add($this->makeMessage());

        //Consume message without acknowledging it, so it stays as pending
        $consumerA->await($consumerA->getNewEntriesKey(), 1);
        sleep(1); // sleep for a moment to get idle time on messages

        $this->assertNotEmpty($consumerA->pending());
        $this->assertEmpty($consumerB->pending());

        $actual = $consumerB->claim($ids, 1, false);
        $this->assertArrayHasKey($ids[0], $actual);
        $this->assertArrayHasKey($ids[1], $actual);
        $this->assertEquals(['foo' => 'bar'], $actual[$ids[0]]);
        $this->assertEquals(['foo' => 'bar'], $actual[$ids[1]]);
    }

    public function test_claim_does_not_assign_not_existing_messages_for_consumer_from_other_consumer(): void
    {
        $stream = new Stream('foo');
        $stream->createGroup('bar');
        $consumerA = new Stream\Consumer('foobar', $stream, 'bar');
        $consumerB = new Stream\Consumer('barfoo', $stream, 'bar');

        $ids = ['1-0', '2-0'];
        //Consume message without acknowledging it, so it stays as pending
        $consumerA->await($consumerA->getNewEntriesKey(), 1);

        $this->assertEmpty($consumerB->claim($ids, 1));
        $this->assertEmpty($consumerB->pending());
    }
}
