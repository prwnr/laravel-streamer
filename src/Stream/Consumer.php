<?php

namespace Prwnr\Streamer\Stream;

use Prwnr\Streamer\Concerns\ConnectsWithRedis;
use Prwnr\Streamer\Exceptions\AcknowledgingFailedException;
use Prwnr\Streamer\Stream;

/**
 * Class Consumer.
 */
class Consumer
{
    use ConnectsWithRedis;

    final public const NEW_ENTRIES = '>';

    /**
     * Consumer constructor.
     */
    public function __construct(
        private readonly string $consumer,
        private readonly Stream $stream,
        private readonly string $group
    ) {
    }

    public function getName(): string
    {
        return $this->stream->getName();
    }

    public function getNewEntriesKey(): string
    {
        return self::NEW_ENTRIES;
    }

    public function await(string $lastSeenId = self::NEW_ENTRIES, int $timeout = 0): ?array
    {
        return $this->redis()->xReadGroup(
            $this->group,
            $this->consumer,
            [$this->stream->getName() => $lastSeenId],
            0,
            $timeout
        );
    }

    /**
     * @throws AcknowledgingFailedException
     */
    public function acknowledge(string $id): void
    {
        $result = $this->redis()->xAck($this->stream->getName(), $this->group, [$id]);
        if ($result === 0) {
            throw new AcknowledgingFailedException(sprintf('Could not acknowledge message with ID %s', $id));
        }
    }

    /**
     * Return pending message only for this particular consumer.
     */
    public function pending(): array
    {
        return $this->stream->pending($this->group, $this->consumer);
    }

    /**
     * Claim all given messages that have minimum idle time of $idleTime milliseconds.
     */
    public function claim(array $ids, int $idleTime, bool $justId = true): array
    {
        if ($justId) {
            return $this->redis()->xClaim(
                $this->stream->getName(),
                $this->group,
                $this->consumer,
                $idleTime,
                $ids,
                ['JUSTID']
            );
        }

        return $this->redis()->xClaim($this->stream->getName(), $this->group, $this->consumer, $idleTime, $ids);
    }
}
