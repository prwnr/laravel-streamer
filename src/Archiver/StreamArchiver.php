<?php

namespace Prwnr\Streamer\Archiver;

use JsonException;
use Prwnr\Streamer\Contracts\Archiver;
use Prwnr\Streamer\Contracts\ArchiveStorage;
use Prwnr\Streamer\EventDispatcher\Message;
use Prwnr\Streamer\EventDispatcher\ReceivedMessage;
use Prwnr\Streamer\Exceptions\ArchivizationFailedException;
use Prwnr\Streamer\Exceptions\RestoringFailedException;
use Prwnr\Streamer\Stream;

class StreamArchiver implements Archiver
{
    private ArchiveStorage $storage;

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
     * @throws JsonException
     */
    public function archive(ReceivedMessage $message): void
    {
        $content = $message->getContent();
        $this->storage->create(new Message([
            '_id' => $message->getId(),
            'original_id' => $content['original_id'] ?? null,
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
     * @throws RestoringFailedException
     * @throws JsonException
     */
    public function restore(Message $message): string
    {
        $result = $this->storage->delete($message->getEventName(), $message->getId());
        if (!$result) {
            throw new RestoringFailedException('Message was not deleted from the archive storage, message will not be restored.');
        }

        $content = $message->getContent();
        $message = new Message([
            'original_id' => $message->getId(),
            'type' => $content['type'],
            'name' => $content['name'],
            'domain' => $content['domain'],
            'created' => $content['created'],
        ], $message->getData());

        $stream = new Stream($message->getEventName());
        $id = $stream->add($message);
        if (!$id) {
            $this->storage->create($message);

            throw new RestoringFailedException('Message was not deleted from the archive storage, message will not be restored.');
        }

        return $id;
    }
}