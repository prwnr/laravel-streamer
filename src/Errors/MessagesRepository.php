<?php

namespace Prwnr\Streamer\Errors;

use Illuminate\Support\Collection;
use Prwnr\Streamer\Concerns\ConnectsWithRedis;
use Prwnr\Streamer\Contracts\Errors\Repository;

class MessagesRepository implements Repository
{
    use ConnectsWithRedis;

    public const ERRORS_SET = 'failed_stream';

    /**
     * @inheritDoc
     */
    public function all(): Collection
    {
        $elements = $this->redis()->sMembers(self::ERRORS_SET);
        if (!$elements) {
            return collect();
        }

        $decode = static fn ($item) => array_values(json_decode($item, true, 512, JSON_THROW_ON_ERROR));

        return collect($elements)
            ->map(static fn($item) => new FailedMessage(...$decode($item)))
            ->sortBy(static fn(FailedMessage $message) => $message->getDate());
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return $this->redis()->sCard(self::ERRORS_SET);
    }

    /**
     * @inheritDoc
     */
    public function add(FailedMessage $message): void
    {
        $this->redis()->sAdd(self::ERRORS_SET, json_encode($message, JSON_THROW_ON_ERROR));
    }

    /**
     * @inheritDoc
     */
    public function remove(FailedMessage $message): void
    {
        $this->redis()->sRem(self::ERRORS_SET, json_encode($message, JSON_THROW_ON_ERROR));
    }

    /**
     * @inheritDoc
     */
    public function flush(): void
    {
        $this->redis()->spop(self::ERRORS_SET, $this->count());
    }
}