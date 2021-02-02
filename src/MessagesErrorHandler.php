<?php

namespace Prwnr\Streamer;

use Exception;
use Prwnr\Streamer\Concerns\ConnectsWithRedis;
use Prwnr\Streamer\Contracts\ErrorHandler;
use Prwnr\Streamer\Contracts\MessageReceiver;
use Prwnr\Streamer\EventDispatcher\ReceivedMessage;
use Prwnr\Streamer\Stream\Range;

class MessagesErrorHandler implements ErrorHandler
{
    use ConnectsWithRedis;
    
    public const ERRORS_LIST = 'failed_streams';

    /**
     * @inheritDoc
     */
    public function handle(ReceivedMessage $message, MessageReceiver $receiver, Exception $e): void
    {
        $data = [
            'id' => $message->getId(),
            'stream' => $message->getContent()['name'] ?? null,
            'receiver' => get_class($receiver),
            'error' => $e->getMessage(),
        ];

        $this->redis()->sAdd(self::ERRORS_LIST, json_encode($data));
    }

    /**
     * @inheritDoc
     */
    public function list(): array
    {
        $count = $this->redis()->sCard(self::ERRORS_LIST);
        if (!$count) {
            return [];
        }

        return array_map(static function ($item) {
            return json_decode($item, true);
        }, $this->redis()->spop(self::ERRORS_LIST, $count));
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function retryAll(): void
    {
        foreach ($this->list() as $failedMessage) {
            $stream = new Stream($failedMessage['stream']);
            $this->retry($stream, $failedMessage['id'], $failedMessage['receiver']);
        }
    }

    /**
     * @inheritDoc
     * @param  Stream  $stream
     * @param  string  $id
     * @param  string  $receiver
     * @throws Exception
     */
    public function retry(Stream $stream, string $id, string $receiver): void
    {
        if (!class_exists($receiver)) {
            return;
        }

        $listener = app($receiver);
        if (!$listener instanceof MessageReceiver) {
            return;
        }

        $messages = $stream->readRange(new Range($id, $id), 1);
        if (!$messages) {
            return;
        }

        foreach ($messages as $message) {
            $receivedMessage = null;

            try {
                $receivedMessage = new ReceivedMessage($message['_id'], $message);
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