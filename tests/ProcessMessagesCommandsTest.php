<?php

declare(strict_types=1);

namespace Tests;

use Carbon\Carbon;
use Exception;
use Illuminate\Foundation\Testing\Concerns\InteractsWithRedis;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;
use Prwnr\Streamer\Concerns\ConnectsWithRedis;
use Prwnr\Streamer\Contracts\Archiver;
use Prwnr\Streamer\EventDispatcher\Message;
use Prwnr\Streamer\EventDispatcher\ReceivedMessage;
use Prwnr\Streamer\Stream;

class ProcessMessagesCommandsTest extends TestCase
{
    use InteractsWithRedis;
    use ConnectsWithRedis;
    use WithMemoryManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRedis();
        $this->redis['phpredis']->connection()->flushall();

        $this->setUpMemoryManager();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->redis['phpredis']->connection()->flushall();
        $this->tearDownRedis();
    }

    public function test_cannot_process_messages_without_streams_list_given(): void
    {
        $this->artisan('streamer:archive', ['--older_than' => '60 minutes'])
            ->expectsOutput('Streams option is required with at least one stream name provided.')
            ->assertExitCode(1);

        $this->artisan('streamer:purge', ['--older_than' => '60 minutes'])
            ->expectsOutput('Streams option is required with at least one stream name provided.')
            ->assertExitCode(1);
    }

    public function test_archives_messages_from_one_stream(): void
    {
        $stream = new Stream('foo.bar');
        $stream->add(
            new Message(
                ['name' => 'foo.bar', 'created' => Carbon::create('-10 days')->timestamp],
                ['foo' => 'first']
            ),
            '123'
        );
        $stream->add(
            new Message(
                ['name' => 'foo.bar', 'created' => Carbon::create('-5 days')->timestamp],
                ['foo' => 'second']
            ),
            '234'
        );
        $stream->add(new Message([
            'name' => 'foo.bar',
            'created' => Carbon::create('-3 days')->addSeconds(10)->timestamp,
        ], ['foo' => 'third']), '345');
        $stream->add(
            new Message(
                ['name' => 'foo.bar', 'created' => Carbon::create('-1 days')->timestamp],
                ['foo' => 'fourth']
            ),
            '456'
        );

        $this->assertCount(4, $stream->read()['foo.bar']);

        $this->artisan('streamer:archive', ['--older_than' => '3 days', '--streams' => 'foo.bar'])
            ->expectsOutput("Message [123-0] has been archived from the 'foo.bar' stream.")
            ->expectsOutput("Message [234-0] has been archived from the 'foo.bar' stream.")
            ->expectsOutput('Total of 2 messages processed.')
            ->assertExitCode(0);

        $messages = $stream->read()['foo.bar'];
        $this->assertCount(2, $messages);
        $this->assertEquals('{"foo":"third"}', array_shift($messages)['data']);
        $this->assertEquals('{"foo":"fourth"}', array_shift($messages)['data']);
        $this->assertCount(2, $this->manager->driver('memory')->all());

        $this->artisan('streamer:archive', ['--older_than' => '60 minutes', '--streams' => 'foo.bar'])
            ->expectsOutput("Message [345-0] has been archived from the 'foo.bar' stream.")
            ->expectsOutput("Message [456-0] has been archived from the 'foo.bar' stream.")
            ->expectsOutput('Total of 2 messages processed.')
            ->assertExitCode(0);

        $this->assertCount(0, $stream->read());
        $this->assertCount(4, $this->manager->driver('memory')->all());
    }

    public function test_archives_messages_from_multiple_streams(): void
    {
        $streamA = new Stream('foo.barA');
        $streamA->add(
            new Message(
                ['name' => 'foo.barA', 'created' => Carbon::create('-5 days')->timestamp],
                ['foo' => 'first']
            ),
            '123'
        );
        $streamA->add(
            new Message(
                ['name' => 'foo.barA', 'created' => Carbon::create('-1 days')->timestamp],
                ['foo' => 'second']
            ),
            '234'
        );

        $streamB = new Stream('foo.barB');
        $streamB->add(
            new Message(
                ['name' => 'foo.barB', 'created' => Carbon::create('-5 days')->timestamp],
                ['foo' => 'first']
            ),
            '123'
        );
        $streamB->add(
            new Message(
                ['name' => 'foo.barB', 'created' => Carbon::create('-1 days')->timestamp],
                ['foo' => 'second']
            ),
            '234'
        );

        $this->assertCount(2, $streamA->read()['foo.barA']);
        $this->assertCount(2, $streamB->read()['foo.barB']);

        $this->artisan('streamer:archive', ['--older_than' => '3 days', '--streams' => 'foo.barA,foo.barB'])
            ->expectsOutput("Message [123-0] has been archived from the 'foo.barA' stream.")
            ->expectsOutput("Message [123-0] has been archived from the 'foo.barB' stream.")
            ->expectsOutput('Total of 2 messages processed.')
            ->assertExitCode(0);

        $this->assertCount(1, $streamA->read()['foo.barA']);
        $this->assertCount(1, $streamB->read()['foo.barB']);
        $this->assertEquals('{"foo":"second"}', array_shift($streamA->read()['foo.barA'])['data']);
        $this->assertEquals('{"foo":"second"}', array_shift($streamB->read()['foo.barB'])['data']);
        $this->assertCount(2, $this->manager->driver('memory')->all());

        $this->artisan('streamer:archive', ['--older_than' => '60 minutes', '--streams' => 'foo.barA,foo.barB'])
            ->expectsOutput("Message [234-0] has been archived from the 'foo.barA' stream.")
            ->expectsOutput("Message [234-0] has been archived from the 'foo.barB' stream.")
            ->expectsOutput('Total of 2 messages processed.')
            ->assertExitCode(0);

        $this->assertCount(0, $streamA->read());
        $this->assertCount(0, $streamB->read());
        $this->assertCount(4, $this->manager->driver('memory')->all());
    }

    public function test_fails_to_archive_message(): void
    {
        $stream = new Stream('foo.bar');
        $stream->add(
            new Message(
                ['name' => 'foo.bar', 'created' => Carbon::create('-10 days')->timestamp],
                ['foo' => 'first']
            ),
            '123'
        );

        $this->assertCount(1, $stream->read()['foo.bar']);

        $mock = $this->mock(Archiver::class);
        $mock->shouldReceive('archive')
            ->with(ReceivedMessage::class)
            ->andThrow(Exception::class, 'unknown');

        $this->artisan('streamer:archive', ['--older_than' => '3 days', '--streams' => 'foo.bar'])
            ->expectsOutput("Message [123-0] from the 'foo.bar' stream could not be archived. Error: unknown")
            ->expectsOutput('Total of 1 message processed.')
            ->assertExitCode(0);

        $this->assertCount(1, $stream->read()['foo.bar']);
        $this->assertCount(0, $this->manager->driver('memory')->all());
    }

    public function test_fails_to_archive_messages_from_non_existing_stream(): void
    {
        $this->artisan('streamer:archive', ['--older_than' => '1 days', '--streams' => 'foo.bar'])
            ->expectsOutput('Total of 0 messages processed.')
            ->assertExitCode(0);
    }

    public function test_purges_messages_from_one_stream(): void
    {
        $stream = new Stream('foo.bar');
        $stream->add(
            new Message(
                ['name' => 'foo.bar', 'created' => Carbon::create('-10 days')->timestamp],
                ['foo' => 'first']
            ),
            '123'
        );
        $stream->add(
            new Message(
                ['name' => 'foo.bar', 'created' => Carbon::create('-5 days')->timestamp],
                ['foo' => 'second']
            ),
            '234'
        );
        $stream->add(new Message([
            'name' => 'foo.bar',
            'created' => Carbon::create('-3 days')->addSeconds(10)->timestamp,
        ], ['foo' => 'third']), '345');
        $stream->add(
            new Message(
                ['name' => 'foo.bar', 'created' => Carbon::create('-1 days')->timestamp],
                ['foo' => 'fourth']
            ),
            '456'
        );

        $this->assertCount(4, $stream->read()['foo.bar']);

        $this->artisan('streamer:purge', ['--older_than' => '3 days', '--streams' => 'foo.bar'])
            ->expectsOutput("Message [123-0] has been purged from the 'foo.bar' stream.")
            ->expectsOutput("Message [234-0] has been purged from the 'foo.bar' stream.")
            ->expectsOutput('Total of 2 messages processed.')
            ->assertExitCode(0);

        $messages = $stream->read()['foo.bar'];
        $this->assertCount(2, $messages);
        $this->assertEquals('{"foo":"third"}', array_shift($messages)['data']);
        $this->assertEquals('{"foo":"fourth"}', array_shift($messages)['data']);

        $this->artisan('streamer:purge', ['--older_than' => '60 minutes', '--streams' => 'foo.bar'])
            ->expectsOutput("Message [345-0] has been purged from the 'foo.bar' stream.")
            ->expectsOutput("Message [456-0] has been purged from the 'foo.bar' stream.")
            ->expectsOutput('Total of 2 messages processed.')
            ->assertExitCode(0);

        $this->assertCount(0, $stream->read());
    }

    public function test_purges_messages_from_multiple_streams(): void
    {
        $streamA = new Stream('foo.barA');
        $streamA->add(
            new Message(
                ['name' => 'foo.barA', 'created' => Carbon::create('-5 days')->timestamp],
                ['foo' => 'first']
            ),
            '123'
        );
        $streamA->add(
            new Message(
                ['name' => 'foo.barA', 'created' => Carbon::create('-1 days')->timestamp],
                ['foo' => 'second']
            ),
            '234'
        );

        $streamB = new Stream('foo.barB');
        $streamB->add(
            new Message(
                ['name' => 'foo.barB', 'created' => Carbon::create('-5 days')->timestamp],
                ['foo' => 'first']
            ),
            '123'
        );
        $streamB->add(
            new Message(
                ['name' => 'foo.barB', 'created' => Carbon::create('-1 days')->timestamp],
                ['foo' => 'second']
            ),
            '234'
        );

        $this->assertCount(2, $streamA->read()['foo.barA']);
        $this->assertCount(2, $streamB->read()['foo.barB']);

        $this->artisan('streamer:purge', ['--older_than' => '3 days', '--streams' => 'foo.barA,foo.barB'])
            ->expectsOutput("Message [123-0] has been purged from the 'foo.barA' stream.")
            ->expectsOutput("Message [123-0] has been purged from the 'foo.barB' stream.")
            ->expectsOutput('Total of 2 messages processed.')
            ->assertExitCode(0);

        $this->assertCount(1, $streamA->read()['foo.barA']);
        $this->assertCount(1, $streamB->read()['foo.barB']);
        $this->assertEquals('{"foo":"second"}', array_shift($streamA->read()['foo.barA'])['data']);
        $this->assertEquals('{"foo":"second"}', array_shift($streamB->read()['foo.barB'])['data']);

        $this->artisan('streamer:purge', ['--older_than' => '60 minutes', '--streams' => 'foo.barA,foo.barB'])
            ->expectsOutput("Message [234-0] has been purged from the 'foo.barA' stream.")
            ->expectsOutput("Message [234-0] has been purged from the 'foo.barB' stream.")
            ->expectsOutput('Total of 2 messages processed.')
            ->assertExitCode(0);

        $this->assertCount(0, $streamA->read());
        $this->assertCount(0, $streamB->read());
    }

    public function test_fails_to_purge_message(): void
    {
        $timestamp = Carbon::create('-10 days')->timestamp;

        $mock = $this->mock(Connection::class);
        Redis::shouldReceive('connection')->andReturn($mock);
        $mock->shouldReceive('setOption')->withAnyArgs();
        $mock->shouldReceive('xRead')->with(['foo.bar' => 0])->andReturn([
            'foo.bar' => [
                '123-0' => [
                    'created' => $timestamp,
                ],
            ],
        ]);
        $mock->shouldReceive('xDel')->with('foo.bar', ['123-0'])->once()->andReturnFalse();

        $this->artisan('streamer:purge', ['--older_than' => '3 days', '--streams' => 'foo.bar'])
            ->expectsOutput("Message [123-0] from the 'foo.bar' stream could not be purged or is already deleted.")
            ->expectsOutput('Total of 1 message processed.')
            ->assertExitCode(0);

        $mock->shouldReceive('xDel')
            ->with('foo.bar', ['123-0'])
            ->once()
            ->andThrow(Exception::class, 'unknown');

        $this->artisan('streamer:purge', ['--older_than' => '3 days', '--streams' => 'foo.bar'])
            ->expectsOutput("Message [123-0] from the 'foo.bar' stream could not be purged. Error: unknown")
            ->expectsOutput('Total of 1 message processed.')
            ->assertExitCode(0);
    }

    public function test_fails_to_purge_messages_from_non_existing_stream(): void
    {
        $this->artisan('streamer:purge', ['--older_than' => '1 days', '--streams' => 'foo.bar'])
            ->expectsOutput('Total of 0 messages processed.')
            ->assertExitCode(0);
    }
}
