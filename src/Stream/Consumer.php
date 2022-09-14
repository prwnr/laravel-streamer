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

    public const NEW_ENTRIES = '>';

    private string $consumer;
    private Stream $stream;
    private string $group;

    /**
     * Consumer constructor.
     *
     * @param string $consumer
     * @param Stream $stream
     * @param string $group
     */
    public function __construct(string $consumer, Stream $stream, string $group)
    {
        $this->stream = $stream;
        $this->group = $group;
        $this->consumer = $consumer;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->stream->getName();
    }

    /**
     * @return string
     */
    public function getNewEntriesKey(): string
    {
        return self::NEW_ENTRIES;
    }

    /**
     * @param  string  $lastSeenId
     * @param  int  $timeout
     * @return array|null
     */
    public function await(string $lastSeenId = self::NEW_ENTRIES, int $timeout = 0): array
    {
        $result = $this->redis()->xReadGroup(
            $this->group, $this->consumer, [$this->stream->getName() => $lastSeenId], 0, $timeout
        );

        if (!is_array($result)) {
            return [];
        }

        return $result;
    }

    /**
     * @param  string  $id
     * @return void
     * @throws AcknowledgingFailedException
     */
    public function acknowledge(string $id): void
    {
        $result = $this->redis()->xAck($this->stream->getName(), $this->group, [$id]);
        if ($result === 0) {
            throw new AcknowledgingFailedException("Could not acknowledge message with ID $id");
        }
    }

    /**
     * Return pending message only for this particular consumer.
     *
     * @return array
     */
    public function pending(): array
    {
        return $this->stream->pending($this->group, $this->consumer);
    }

    /**
     * Claim all given messages that have minimum idle time of $idleTime milliseconds.
     *
     * @param  array  $ids
     * @param  int  $idleTime
     * @param  bool  $justId
     *
     * @return array
     */
    public function claim(array $ids, int $idleTime, bool $justId = true): array
    {
        if ($justId) {
            return $this->redis()->xClaim($this->stream->getName(), $this->group, $this->consumer, $idleTime, $ids,
                ['JUSTID']);
        }

        return $this->redis()->xClaim($this->stream->getName(), $this->group, $this->consumer, $idleTime, $ids);
    }
}
