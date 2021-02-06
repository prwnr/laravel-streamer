<?php

namespace Prwnr\Streamer;

use Exception;
use Illuminate\Support\Collection;
use Prwnr\Streamer\Concerns\ConnectsWithRedis;
use Prwnr\Streamer\Contracts\ErrorHandler;
use Prwnr\Streamer\Contracts\MessageReceiver;
use Prwnr\Streamer\EventDispatcher\ReceivedMessage;
use Prwnr\Streamer\Stream\Range;

class MessagesErrorHandler implements ErrorHandler
{
    use ConnectsWithRedis;
    
    public const ERRORS_SET = 'failed_streams';

    /**
     * @inheritDoc
     */
    public function handle(ReceivedMessage $message, MessageReceiver $receiver, Exception $e): void
    {
        $this->redis()->sAdd(self::ERRORS_SET, json_encode(new FailedMessage(...[
            $message->getId(),
            $message->getContent()['name'] ?? '',
            get_class($receiver),
            $e->getMessage(),
        ])));
    }

    /**
     * @inheritDoc
     */
    public function list(): Collection
    {
        $elements = $this->redis()->sMembers(self::ERRORS_SET);
        if (!$elements) {
            return collect();
        }

        return collect($elements)->map(static function ($item) {
            return new FailedMessage(...array_values(json_decode($item, true)));
        });
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function retryAll(): void
    {
        foreach ($this->list() as $failedMessage) {
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

        $this->redis()->sRem(self::ERRORS_SET, json_encode($message));

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