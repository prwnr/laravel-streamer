<?php

declare(strict_types=1);

namespace Tests;

use Exception;
use Illuminate\Foundation\Testing\Concerns\InteractsWithRedis;
use Prwnr\Streamer\Concerns\ConnectsWithRedis;
use Prwnr\Streamer\Contracts\Archiver;
use Prwnr\Streamer\EventDispatcher\Message;
use Prwnr\Streamer\Stream;

class ArchiveRestoreCommandTest extends ArchiveTestCase
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

    public function test_restoring_message_assigns_new_id_for_it(): void
    {
        $message = $this->addArchiveMessage('foo.bar', '123-0', ['foo' => 'bar']);

        $mock = $this->mock(Archiver::class);
        $mock->shouldReceive('restore')
            ->with($message)
            ->andReturn('234-0');

        $this->artisan('streamer:archive:restore', ['--all' => true])
            ->expectsQuestion(
                'Restoring a message will add it back onto the stream and will trigger listeners hooked to its event. Do you want to continue?',
                true
            )
            ->expectsOutput('Successfully restored [foo.bar][123-0] message. New ID: 234-0')
            ->assertExitCode(0);
    }

    public function test_restores_all_messages_from_archive(): void
    {
        $this->addArchiveMessage('foo.bar', '123-0', ['foo' => 'bar']);
        $this->addArchiveMessage('foo.bar', '345-0', ['foo' => 'bar']);
        $this->addArchiveMessage('foo', '123-0', ['foo' => 'bar']);

        $fooBarStream = new Stream('foo.bar');
        $fooStream = new Stream('foo');

        $this->assertCount(0, $fooBarStream->read());
        $this->assertCount(0, $fooStream->read());
        $this->assertCount(3, $this->manager->driver('memory')->all());

        $this->artisan('streamer:archive:restore', ['--all' => true])
            ->expectsQuestion(
                'Restoring a message will add it back onto the stream and will trigger listeners hooked to its event. Do you want to continue?',
                true
            )
            ->assertExitCode(0);

        $this->assertCount(2, $fooBarStream->read()['foo.bar']);
        $this->assertCount(1, $fooStream->read()['foo']);
        $this->assertCount(0, $this->manager->driver('memory')->all());
    }

    public function test_restores_all_messages_from_a_given_stream(): void
    {
        $this->addArchiveMessage('foo.bar', '123-0', ['foo' => 'bar']);
        $this->addArchiveMessage('foo.bar', '345-0', ['foo' => 'bar']);
        $this->addArchiveMessage('foo', '123-0', ['foo' => 'bar']);

        $fooBarStream = new Stream('foo.bar');
        $fooStream = new Stream('foo');

        $this->assertCount(0, $fooBarStream->read());
        $this->assertCount(0, $fooStream->read());
        $this->assertCount(3, $this->manager->driver('memory')->all());

        $this->artisan('streamer:archive:restore', ['--stream' => 'foo.bar'])
            ->expectsQuestion(
                'Restoring a message will add it back onto the stream and will trigger listeners hooked to its event. Do you want to continue?',
                true
            )
            ->assertExitCode(0);

        $this->assertCount(2, $fooBarStream->read()['foo.bar']);
        $this->assertCount(0, $fooStream->read());
        $this->assertCount(1, $this->manager->driver('memory')->all());
    }

    public function test_restores_message_by_id(): void
    {
        $this->addArchiveMessage('foo.bar', '123-0', ['foo' => 'bar']);
        $this->addArchiveMessage('foo.bar', '345-0', ['foo' => 'bar']);
        $this->addArchiveMessage('foo', '123-0', ['foo' => 'bar']);

        $fooBarStream = new Stream('foo.bar');
        $fooStream = new Stream('foo');

        $this->assertCount(0, $fooBarStream->read());
        $this->assertCount(0, $fooStream->read());
        $this->assertCount(3, $this->manager->driver('memory')->all());

        $this->artisan('streamer:archive:restore', ['--stream' => 'foo.bar', '--id' => '345-0'])
            ->expectsQuestion(
                'Restoring a message will add it back onto the stream and will trigger listeners hooked to its event. Do you want to continue?',
                true
            )
            ->assertExitCode(0);

        $this->assertCount(1, $fooBarStream->read()['foo.bar']);
        $this->assertCount(0, $fooStream->read());
        $this->assertCount(2, $this->manager->driver('memory')->all());
    }

    public function test_wont_restore_message_by_id_with_missing_stream_name(): void
    {
        $this->artisan('streamer:archive:restore', ['--id' => '345-0'])
            ->expectsQuestion(
                'Restoring a message will add it back onto the stream and will trigger listeners hooked to its event. Do you want to continue?',
                true
            )
            ->expectsOutput('To restore by ID, a stream name needs to be provided as well.')
            ->assertExitCode(1);
    }

    public function test_wont_restore_message_by_id_if_its_not_archived(): void
    {
        $this->artisan('streamer:archive:restore', ['--stream' => 'foo.bar', '--id' => '345-0'])
            ->expectsQuestion(
                'Restoring a message will add it back onto the stream and will trigger listeners hooked to its event. Do you want to continue?',
                true
            )
            ->expectsOutput('The message could not be found in archive storage.')
            ->assertExitCode(1);
    }

    public function test_wont_restore_any_message_if_none_of_the_options_are_selected(): void
    {
        $this->artisan('streamer:archive:restore')
            ->expectsQuestion(
                'Restoring a message will add it back onto the stream and will trigger listeners hooked to its event. Do you want to continue?',
                true
            )
            ->expectsOutput('At least one option must be used to restore the message.')
            ->assertExitCode(1);
    }

    public function test_wont_restore_message_if_restoration_fails_at_any_point(): void
    {
        $this->addArchiveMessage('foo.bar', '123-0', ['foo' => 'bar']);

        $fooBarStream = new Stream('foo.bar');

        $this->assertCount(0, $fooBarStream->read());
        $this->assertCount(1, $this->manager->driver('memory')->all());

        $mock = $this->mock(Archiver::class);
        $mock->shouldReceive('restore')
            ->with(Message::class)
            ->andThrow(Exception::class, 'something went wrong');

        $this->artisan('streamer:archive:restore', ['--stream' => 'foo.bar', '--id' => '123-0'])
            ->expectsQuestion(
                'Restoring a message will add it back onto the stream and will trigger listeners hooked to its event. Do you want to continue?',
                true
            )
            ->expectsOutput("Failed to restore [foo.bar][123-0] message. Error: something went wrong")
            ->assertExitCode(0);

        $this->assertCount(0, $fooBarStream->read());
        $this->assertCount(1, $this->manager->driver('memory')->all());
    }
}
