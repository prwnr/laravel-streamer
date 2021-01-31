<?php

namespace Prwnr\Streamer\Contracts;

use Exception;
use Prwnr\Streamer\EventDispatcher\ReceivedMessage;
use Prwnr\Streamer\Stream;

interface ErrorHandler
{
    /**
     * Stores failed message information in a list for later retry attempt.
     *
     * @param  ReceivedMessage  $message
     * @param  MessageReceiver  $receiver
     * @param  Exception  $e
     * @return mixed
     */
    public function handle(ReceivedMessage $message, MessageReceiver $receiver, Exception $e): void;

    /**
     * Returns a list of all failed messages info.
     *
     * @return array
     */
    public function list(): array;

    /**
     * Iterates over all failed messages and passes them through their associated listeners.
     */
    public function retryAll(): void;

    /**
     * Looks up message on a stream and attempts to retry it with given receiver.
     *
     * @param  Stream  $stream
     * @param  string  $id
     * @param  string  $receiver
     */
    public function retry(Stream $stream, string $id, string $receiver): void;
}