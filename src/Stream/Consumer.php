<?php

namespace Prwnr\Streamer\Stream;

use Exception;
use Prwnr\Streamer\Concerns\ConnectsWithRedis;
use Prwnr\Streamer\Contracts\Waitable;
use Prwnr\Streamer\Stream;

/**
 * Class Consumer.
 */
class Consumer implements Waitable
{
    use ConnectsWithRedis;

    public const NEW_ENTRIES = '>';

    /**
     * @var string
     */
    private $consumer;

    /**
     * @var Stream
     */
    private $stream;

    /**
     * @var string
     */
    private $group;

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
     * {@inheritdoc}
     */
    public function await(string $lastId = self::NEW_ENTRIES, int $timeout = 0): ?array
    {
        return $this->redis()->xReadGroup(
            $this->group, $this->consumer, [$this->stream->getName() => $lastId], null, $timeout
        );
    }

    /**
     * {@inheritdoc}
     *
     * @throws Exception
     */
    public function acknowledge(string $id): void
    {
        $result = $this->redis()->XACK($this->stream->getName(), $this->group, [$id]);
        if ($result === 0) {
            throw new Exception("Could not acknowledge message with ID $id");
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
     * Claim all given messages that have minimum idle time of $idleTime miliseconds.
     *
     * @param array $ids
     * @param int   $idleTime
     * @param bool  $justId
     *
     * @return array
     */
    public function claim(array $ids, int $idleTime, $justId = true): array
    {
        if ($justId) {
            return $this->redis()->xClaim($this->stream->getName(), $this->group, $this->consumer, $idleTime, $ids, ['JUSTID']);
        }

        return $this->redis()->xClaim($this->stream->getName(), $this->group, $this->consumer, $idleTime, $ids);
    }
}
