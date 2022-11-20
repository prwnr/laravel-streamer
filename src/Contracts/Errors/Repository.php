<?php

declare(strict_types=1);

namespace Prwnr\Streamer\Contracts\Errors;

use Illuminate\Support\Collection;
use Prwnr\Streamer\Errors\FailedMessage;

interface Repository
{
    /**
     * Returns all failed stream messages.
     *
     * @return Collection&FailedMessage[]
     */
    public function all(): Collection;

    /**
     * Returns how many failed messages is there.
     */
    public function count(): int;

    /**
     * Adds new failed message.
     */
    public function add(FailedMessage $message): void;

    /**
     * Removes existing message.
     */
    public function remove(FailedMessage $message): void;

    /**
     * Removes all failed messages.
     */
    public function flush(): void;
}
