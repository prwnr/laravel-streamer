<?php

namespace Tests;

use Illuminate\Foundation\Testing\Concerns\InteractsWithRedis;
use Prwnr\Streamer\Concerns\ConnectsWithRedis;
use Prwnr\Streamer\Errors\FailedMessage;
use Prwnr\Streamer\Errors\MessagesRepository;
use Tests\Stubs\LocalListener;

class ErrorsMessagesRepositoryTest extends TestCase
{
    use InteractsWithRedis;
    use ConnectsWithRedis;

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

    public function test_lists_all_failed_messages_info(): void
    {
        $repository = new MessagesRepository();

        $this->assertCount(0, $repository->all());

        $this->failFakeMessage('foo.bar', '123', ['payload' => 123]);
        $this->failFakeMessage('other.bar', '321', ['payload' => 321]);
        $this->failFakeMessage('some.bar', '456', ['payload' => 456]);

        $actual = $repository->all();

        $this->assertCount(3, $actual);
        $this->assertEquals([
            'id' => '123',
            'stream' => 'foo.bar',
            'receiver' => LocalListener::class,
            'error' => 'error',
        ], $actual->first(static function (FailedMessage $item) {
            return $item->getId() === '123';
        })->jsonSerialize());
        $this->assertEquals([
            'id' => '321',
            'stream' => 'other.bar',
            'receiver' => LocalListener::class,
            'error' => 'error',
        ], $actual->first(static function (FailedMessage $item) {
            return $item->getId() === '321';
        })->jsonSerialize());
        $this->assertEquals([
            'id' => '456',
            'stream' => 'some.bar',
            'receiver' => LocalListener::class,
            'error' => 'error',
        ], $actual->first(static function (FailedMessage $item) {
            return $item->getId() === '456';
        })->jsonSerialize());
    }

    public function test_adds_new_failed_message(): void
    {
        $repository = new MessagesRepository();
        $message = new FailedMessage('123', 'foo.bar', 'receiver', 'error');

        $repository->add($message);

        $this->assertEquals(1, $repository->count());
        $this->assertEquals($message, $repository->all()->first());
    }

    public function test_removes_message(): void
    {
        $repository = new MessagesRepository();
        $message = new FailedMessage('123', 'foo.bar', 'receiver', 'error');

        $repository->add($message);
        $this->assertEquals(1, $repository->count());

        $repository->remove($message);

        $this->assertEquals(0, $repository->count());
    }
}