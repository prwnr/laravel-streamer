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
     * @throws MessageRetryFailedException
     * @throws Throwable
     */
    public function retry(FailedMessage $message): void
    {
        $listener = $this->makeReceiver($message);

        $receivedMessage = null;
        try {
            $receivedMessage = new ReceivedMessage($message->getId(), $message->getStreamMessage());
            $listener->handle($receivedMessage);
        } catch (Throwable $e) {
            if ($receivedMessage === null) {
                throw $e;
            }

            $this->store($receivedMessage, $listener, $e);

            throw new MessageRetryFailedException($message, $e->getMessage());
        } finally {
            $this->repository->remove($message);
        }
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
     * @throws MessageRetryFailedException
     */
    private function makeReceiver(FailedMessage $message): MessageReceiver
    {
        if (!class_exists($message->getReceiver())) {
            throw new MessageRetryFailedException($message, 'Receiver class does not exists');
        }

        $listener = app($message->getReceiver());
        if (!$listener instanceof MessageReceiver) {
            throw new MessageRetryFailedException(
                $message,
                'Receiver class is not an instance of MessageReceiver contract'
            );
        }

        return $listener;
    }
}
