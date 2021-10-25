<?php

namespace Prwnr\Streamer\Archiver;

use Prwnr\Streamer\Contracts\Archiver;
use Prwnr\Streamer\Contracts\ArchiveStorage;
use Prwnr\Streamer\EventDispatcher\Message;
use Prwnr\Streamer\EventDispatcher\ReceivedMessage;
use Prwnr\Streamer\Exceptions\ArchivizationFailedException;
use Prwnr\Streamer\Stream;

class StreamArchiver implements Archiver
{
    /**
     * @var ArchiveStorage
     */
    private $storage;

    /**
     * StreamArchiver constructor.
     */
    public function __construct(StorageManager $manager)
    {
        $this->storage = $manager->driver(config('streamer.archive.storage_driver'));
    }

    /**
     * @inheritDoc
     * @throws ArchivizationFailedException
     */
    public function archive(ReceivedMessage $message): void
    {
        $content = $message->getContent();
        $this->storage->create(new Message([
            '_id' => $message->getId(),
            'name' => $content['name'],
            'domain' => $content['domain'],
            'created' => $content['created'] ?? null,
        ], $content['data']));

        $stream = new Stream($message->getEventName());
        $result = $stream->delete($message->getId());
        if (!$result) {
            $this->storage->delete($message->getEventName(), $message->getId());

            throw new ArchivizationFailedException('Stream message could not be deleted, message will not be archived.');
        }
    }

    /**
     * @inheritDoc
     */
    public function restore(string $event, string $id): ?Message
    {
        $message = $this->storage->find($event, $id);
        if (!$message) {
            return null;
        }

        $this->storage->delete($event, $id);

        $stream = new Stream($event);
        $stream->add($message, $id);

        return $message;
    }
}