<?php

declare(strict_types=1);

namespace Tests;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\Concerns\InteractsWithRedis;
use Prwnr\Streamer\Contracts\Event;
use Prwnr\Streamer\Contracts\Replayable;
use Prwnr\Streamer\EventDispatcher\Streamer;
use Prwnr\Streamer\History\EventHistory;
use Prwnr\Streamer\Stream;

class ReplayStreamTest extends TestCase
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

    public function testReplaySingleEvent(): void
    {
        $recorder = new EventHistory();
        $streamer = new Streamer($recorder);

        $streamer->emit(new ReplayableFooBarEvent('123', [
            'foo' => 'foo initial',
            'bar' => 'bar initial',
        ]));

        $streamer->emit(new ReplayableFooBarEvent('123', [
            'foo' => 'changed',
            'new bar' => true,
        ]));

        $streamer->emit(new ReplayableFooBarEvent('123', [
            'foo' => 'changed again',
            'bar' => 'different value',
        ]));

        $payload = $recorder->replay('foo.bar', '123');

        $this->assertEquals([
            'foo' => 'changed again',
            'bar' => 'different value',
            'new bar' => true,
        ], $payload);
    }

    public function testReplaysUntilSpecifiedDate(): void
    {
        $recorder = new EventHistory();
        $streamer = new Streamer($recorder);

        $streamer->emit(new ReplayableFooBarEvent('123', [
            'foo' => 'foo initial',
            'bar' => 'bar initial',
        ]));

        $streamer->emit(new ReplayableFooBarEvent('123', [
            'foo' => 'changed',
            'new bar' => true,
        ]));

        $until = Carbon::now();
        sleep(2);

        $streamer->emit(new ReplayableFooBarEvent('123', [
            'foo' => 'changed again',
            'bar' => 'different value',
        ]));

        $payload = $recorder->replay('foo.bar', '123', $until);

        $this->assertEquals([
            'foo' => 'changed',
            'bar' => 'bar initial',
            'new bar' => true,
        ], $payload);
    }

    public function testReplayingSingleEventWithMultipleDifferentEventsEmitted(): void
    {
        $recorder = new EventHistory();
        $streamer = new Streamer($recorder);

        $streamer->emit(new ReplayableFooBarEvent('123', [
            'foo' => 'foo',
            'bar' => 'bar',
        ]));

        $streamer->emit(new ReplayableOtherFooBarEvent('123', [
            'other_foo' => 'foo',
            'other_bar' => 'bar',
        ]));

        for ($i = 0; $i < 10; $i++) {
            $streamer->emit(new ReplayableFooBarEvent('123', [
                'foo' => 'foo' . $i,
            ]));

            $streamer->emit(new ReplayableOtherFooBarEvent('123', [
                'other_bar' => 'bar' . $i,
            ]));
        }

        $this->assertEquals([
            'foo' => 'foo9',
            'bar' => 'bar',
        ], $recorder->replay('foo.bar', '123'));

        $this->assertEquals([
            'other_foo' => 'foo',
            'other_bar' => 'bar9',
        ], $recorder->replay('other.foo.bar', '123'));
    }

    public function testReplayingSingleEventWithBigHistory(): void
    {
        $recorder = new EventHistory();
        $streamer = new Streamer($recorder);

        $streamer->emit(new ReplayableFooBarEvent('123', [
            'foo' => 'foo',
            'bar' => 'bar',
            'foobar' => 'foobar',
        ]));

        $modChange = 0;
        for ($i = 1; $i <= 1000; $i++) {
            $streamer->emit(new ReplayableFooBarEvent('123', [
                'foo' => 'foo-' . $i,
            ]));

            if (($i % 100) === 0) {
                $modChange++;
                $streamer->emit(new ReplayableFooBarEvent('123', [
                    'foobar' => 'foobar-' . $modChange,
                ]));
            }
        }

        $this->assertEquals([
            'foo' => 'foo-1000',
            'bar' => 'bar',
            'foobar' => 'foobar-10',
        ], $recorder->replay('foo.bar', '123'));
    }

    public function testReplayingFractionOfTheEventWhenSomeMessagesAreDeleted(): void
    {
        $recorder = new EventHistory();
        $streamer = new Streamer($recorder);

        $streamer->emit(new ReplayableFooBarEvent('123', [
            'foo' => 'foo initial',
            'bar' => 'bar initial',
            'new bar' => false,
        ]));

        $deleted = $streamer->emit(new ReplayableFooBarEvent('123', [
            'foo' => 'changed',
            'new bar' => true,
        ]));

        $streamer->emit(new ReplayableFooBarEvent('123', [
            'foo' => 'changed again',
            'bar' => 'different value',
        ]));

        $stream = new Stream('foo.bar');
        $stream->delete($deleted);

        $payload = $recorder->replay('foo.bar', '123');

        $this->assertEquals([
            'foo' => 'changed again',
            'bar' => 'different value',
            'new bar' => false,
        ], $payload);
    }
}

abstract class StreamerReplayableEvent implements Event, Replayable
{
    public function __construct(protected string $id, protected array $payload)
    {
    }

    public function name(): string
    {
        return 'foo.bar';
    }

    public function type(): string
    {
        return Event::TYPE_EVENT;
    }

    public function payload(): array
    {
        return $this->payload;
    }

    public function getIdentifier(): string
    {
        return $this->id;
    }
}

class ReplayableFooBarEvent extends StreamerReplayableEvent
{
    public function name(): string
    {
        return 'foo.bar';
    }
}

class ReplayableOtherFooBarEvent extends StreamerReplayableEvent
{
    public function name(): string
    {
        return 'other.foo.bar';
    }
}
