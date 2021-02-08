<?php

namespace Prwnr\Streamer\Errors;

use Exception;
use Prwnr\Streamer\Contracts\Errors\ErrorHandler;
use Prwnr\Streamer\Contracts\Errors\Repository;
use Prwnr\Streamer\Contracts\MessageReceiver;
use Prwnr\Streamer\EventDispatcher\ReceivedMessage;
use Prwnr\Streamer\Stream;
use Prwnr\Streamer\Stream\Range;

class MessagesErrorHandler implements ErrorHandler
{
    private $repository;

    /**
     * MessagesErrorHandler constructor.
     *
     * @param  Repository  $repository
     */
    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @inheritDoc
     */
    public function handle(ReceivedMessage $message, MessageReceiver $receiver, Exception $e): void
    {
        $this->repository->add(new FailedMessage(...[
            $message->getId(),
            $message->getContent()['name'] ?? '',
            get_class($receiver),
            $e->getMessage(),
        ]));
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function retryAll(): void
    {
        foreach ($this->repository->all() as $failedMessage) {
            $this->retry($failedMessage);
        }
    }

    /**
     * @inheritDoc
     * @param  Stream  $stream
     * @param  string  $id
     * @param  string  $receiver
     * @throws Exception
     */
    public function retry(FailedMessage $message): void
    {
        if (!class_exists($message->getReceiver())) {
            return;
        }

        $listener = app($message->getReceiver());
        if (!$listener instanceof MessageReceiver) {
            return;
        }

        $this->repository->remove($message);

        $range = new Range($message->getId(), $message->getId());
        $messages = $message->getStream()->readRange($range, 1);
        if (!$messages) {
            return;
        }

        foreach ($messages as $streamMessage) {
            $receivedMessage = null;

            try {
                $receivedMessage = new ReceivedMessage($streamMessage['_id'], $streamMessage);
                $listener->handle($receivedMessage);
            } catch (Exception $e) {
                if (!$receivedMessage) {
                    throw $e;
                }

                $this->handle($receivedMessage, $listener, $e);
            }
        }
    }
}