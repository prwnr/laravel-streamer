<?php

namespace Prwnr\Streamer;

use Exception;
use Illuminate\Contracts\Container\BindingResolutionException;
use JsonException;
use Prwnr\Streamer\Concerns\ConnectsWithRedis;
use Prwnr\Streamer\Contracts\ErrorHandler;
use Prwnr\Streamer\Contracts\MessageReceiver;
use Prwnr\Streamer\EventDispatcher\ReceivedMessage;
use Prwnr\Streamer\Stream\Range;

class MessagesErrorHandler implements ErrorHandler
{
    use ConnectsWithRedis;

    /**
     * @inheritDoc
     * @throws JsonException
     */
    public function handle(ReceivedMessage $message, MessageReceiver $receiver, Exception $e): void
    {
        $data = [
            'id' => $message->getId(),
            'stream' => $message->getContent()['name'] ?? null,
            'receiver' => get_class($receiver),
            'error' => $e->getMessage(),
        ];

        $this->redis()->lPush('failed_streams', json_encode($data, JSON_THROW_ON_ERROR));
    }

    /**
     * @inheritDoc
     * @throws JsonException
     */
    public function retryAll(): void
    {
        do {
            $failed = $this->redis()->rPop('failed_streams');
            if ($failed) {
                $data = json_decode($failed, true, 512, JSON_THROW_ON_ERROR);

                $stream = new Stream($data['stream']);
                $this->retry($stream, $data['id'], $data['receiver']);
            }
        } while ($failed);
    }

    /**
     * @inheritDoc
     * @param  Stream  $stream
     * @param  string  $id
     * @param  string  $receiver
     * @throws BindingResolutionException
     * @throws JsonException
     */
    public function retry(Stream $stream, string $id, string $receiver): void
    {
        if (!class_exists($receiver)) {
            return;
        }

        $messages = $stream->readRange(new Range($id, $id), 1);
        if (!$messages) {
            return;
        }

        /** @var MessageReceiver $listener */
        $listener = app()->make($receiver);
        foreach ($messages as $message) {
            $listener->handle(new ReceivedMessage($message['_id'], $message));
        }
    }
}