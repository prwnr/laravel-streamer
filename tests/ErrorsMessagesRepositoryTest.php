<?php

declare(strict_types=1);

namespace Tests;

use Carbon\Carbon;
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

        Carbon::withTestNow(Carbon::parse('2021-12-12 12:12:12'), function (): void {
            $this->failFakeMessage('foo.bar', '123', ['payload' => 123]);
        });

        Carbon::withTestNow(Carbon::parse('2021-12-12 12:15:12'), function (): void {
            $this->failFakeMessage('other.bar', '321', ['payload' => 321]);
        });

        Carbon::withTestNow(Carbon::parse('2021-12-12 12:20:12'), function (): void {
            $this->failFakeMessage('some.bar', '456', ['payload' => 456]);
        });

        $actual = $repository->all();

        $this->assertCount(3, $actual);
        $this->assertEquals([
            [
                'id' => '123',
                'stream' => 'foo.bar',
                'receiver' => LocalListener::class,
                'error' => 'error',
                'date' => '2021-12-12 12:12:12',
            ],
            [
                'id' => '321',
                'stream' => 'other.bar',
                'receiver' => LocalListener::class,
                'error' => 'error',
                'date' => '2021-12-12 12:15:12',
            ],
            [
                'id' => '456',
                'stream' => 'some.bar',
                'receiver' => LocalListener::class,
                'error' => 'error',
                'date' => '2021-12-12 12:20:12',
            ],
        ], $actual->map(static fn (FailedMessage $message): array => $message->jsonSerialize())->values()->toArray());
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

    public function test_removes_all_messages(): void
    {
        $repository = new MessagesRepository();

        $repository->add(new FailedMessage('123', 'foo.bar', 'receiver', 'error'));
        $repository->add(new FailedMessage('234', 'foo.bar', 'receiver', 'error'));

        $this->assertEquals(2, $repository->count());

        $repository->flush();

        $this->assertEquals(0, $repository->count());
    }
}
