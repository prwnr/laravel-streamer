<?php

declare(strict_types=1);

namespace Prwnr\Streamer\Errors;

use Exception;
use Prwnr\Streamer\Contracts\Errors\MessagesFailer;
use Prwnr\Streamer\Contracts\Errors\Repository;
use Prwnr\Streamer\Contracts\MessageReceiver;
use Prwnr\Streamer\EventDispatcher\ReceivedMessage;
use Prwnr\Streamer\Exceptions\MessageRetryFailedException;
use Throwable;

class FailedMessagesHandler implements MessagesFailer
{
    public function __construct(private readonly Repository $repository)
    {
    }

    /**
     * @inheritDoc
     */
    public function store(ReceivedMessage $message, MessageReceiver $receiver, Exception $e): void
    {
        $this->repository->add(new FailedMessage(...[
            $message->getId(),
            $message->getContent()['name'] ?? '',
            $receiver::class,
            $e->getMessage(),
        ]));
    }

    /**
     * @inheritDoc
     * @throws MessageRetryFailedException
     * @throws Throwable
     */
    public function retry(FailedMessage $message): void
    {
        $listener = $this->makeReceiver($message);

        $receivedMessage = null;
        try {
            $receivedMessage = new ReceivedMessage($message->id, $message->getStreamMessage());
            $listener->handle($receivedMessage);
        } catch (Throwable $throwable) {
            if ($receivedMessage === null) {
                throw $throwable;
            }

            $this->store($receivedMessage, $listener, $throwable);

            throw new MessageRetryFailedException($message, $throwable->getMessage());
        } finally {
            $this->repository->remove($message);
        }
    }

    /**
     * @throws MessageRetryFailedException
     */
    private function makeReceiver(FailedMessage $message): MessageReceiver
    {
        if (!class_exists($message->receiver)) {
            throw new MessageRetryFailedException($message, 'Receiver class does not exists');
        }

        $listener = app($message->receiver);
        if (!$listener instanceof MessageReceiver) {
            throw new MessageRetryFailedException(
                $message,
                'Receiver class is not an instance of MessageReceiver contract'
            );
        }

        return $listener;
    }
}
