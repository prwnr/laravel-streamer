<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\Concerns\InteractsWithRedis;
use Prwnr\Streamer\Archiver\StreamArchiver;
use Prwnr\Streamer\EventDispatcher\Message;
use Prwnr\Streamer\EventDispatcher\ReceivedMessage;
use Prwnr\Streamer\Exceptions\ArchivizationFailedException;
use Prwnr\Streamer\Exceptions\RestoringFailedException;
use Prwnr\Streamer\Stream;

class StreamArchiverTest extends TestCase
{
    use InteractsWithRedis;
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
        $this->redis['phpredis']->connection()->flushall();

        parent::tearDown();

        $this->manager = null;
    }

    public function test_archives_message(): void
    {
        $stream = new Stream('foo.bar');
        $streamedMessage = new Message([
            'name' => 'foo.bar',
            'domain' => 'foo',
        ], ['foo']);

        $id = $stream->add($streamedMessage);
        $received = new ReceivedMessage($id, $streamedMessage->getContent());

        /** @var StreamArchiver $archiver */
        $archiver = $this->app->make(StreamArchiver::class);
        $archiver->archive($received);

        $message = $this->manager->driver('memory')->find('foo.bar', $id);

        $this->assertInstanceOf(Message::class, $message);
        $this->assertEquals($received->getId(), $message->getId());
        $this->assertEquals($received->getEventName(), $message->getEventName());
        $this->assertEquals($received->getData(), $message->getData());
        $this->assertCount(0, $stream->read());
    }

    public function test_wont_archive_message_if_it_cannot_be_deleted_from_stream(): void
    {
        $streamedMessage = new Message([
            '_id' => '123',
            'name' => 'foo.bar',
            'domain' => 'foo',
        ], ['foo']);

        $received = new ReceivedMessage('123', $streamedMessage->getContent());

        $this->expectException(ArchivizationFailedException::class);
        $this->expectExceptionMessage('Stream message could not be deleted, message will not be archived.');

        /** @var StreamArchiver $archiver */
        $archiver = $this->app->make(StreamArchiver::class);
        $archiver->archive($received);

        $this->assertNull($this->manager->driver('memory')->find('foo.bar', '123'));
    }

    public function test_restore_message(): void
    {
        $stream = new Stream('foo.bar');
        $message = new Message([
            '_id' => '123-0',
            'name' => 'foo.bar',
            'domain' => 'foo',
        ], ['foo']);
        $this->manager->driver('memory')->create($message);

        $this->assertCount(0, $stream->read());

        /** @var StreamArchiver $archiver */
        $archiver = $this->app->make(StreamArchiver::class);
        $archiver->restore($message);

        $this->assertNull($this->manager->driver('memory')->find('foo.bar', '123'));

        $messages = $stream->read();
        $this->assertCount(1, $messages);
        $this->assertArrayHasKey('foo.bar', $messages);

        $actualMessage = array_pop($messages['foo.bar']);

        $this->assertEquals('123-0', $actualMessage['original_id']);
        $this->assertEquals($message->getContent()['data'], $actualMessage['data']);
    }

    public function test_wont_restore_not_archived_message(): void
    {
        $stream = new Stream('foo.bar');
        $message = new Message([
            '_id' => '123-0',
            'name' => 'foo.bar',
            'domain' => 'foo',
        ], ['foo']);

        /** @var StreamArchiver $archiver */
        $archiver = $this->app->make(StreamArchiver::class);

        $this->expectException(RestoringFailedException::class);
        $this->expectExceptionMessage(
            'Message was not deleted from the archive storage, message will not be restored.'
        );

        $archiver->restore($message);

        $this->assertCount(0, $stream->read());
        $this->assertNotNull($this->manager->driver('memory')->find('foo.bar', '123'));
    }
}
