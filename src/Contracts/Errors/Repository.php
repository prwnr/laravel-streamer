<?php

namespace Prwnr\Streamer\Contracts\Errors;

use Illuminate\Support\Collection;
use Prwnr\Streamer\Errors\FailedMessage;

interface Repository
{
    /**
     * Returns all failed stream messages
     *
     * @return Collection&FailedMessage[]
     */
    public function all(): Collection;

    /**
     * Returns how many failed messages is there.
     *
     * @return int
     */
    public function count(): int;

    /**
     * Adds new failed message.
     *
     * @param  FailedMessage  $message
     */
    public function add(FailedMessage $message): void;

    /**
     * Removes existing message.
     *
     * @param  FailedMessage  $message
     */
    public function remove(FailedMessage $message): void;

    /**
     * Removes all failed messages.
     */
    public function flush(): void;
}