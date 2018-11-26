<?php


namespace Prwnr\Streamer\Stream;

use Illuminate\Support\Facades\Redis;
use Prwnr\Streamer\Contracts\Waitable;
use Prwnr\Streamer\Stream;

/**
 * Class Consumer
 * @package Prwnr\Streamer\Stream
 */
class Consumer implements Waitable
{
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
    public function await(string $lastId, int $timeout = 0): ?array
    {
        return Redis::XREADGROUP(Stream::GROUP, $this->group, $this->consumer, Stream::BLOCK, $timeout, Stream::STREAMS, $this->stream->getName(), $lastId);
    }

    /**
     * {@inheritdoc}
     * @throws \Exception
     */
    public function acknowledge(string $id): void
    {
        $result = Redis::XACK($this->stream->getName(), $this->group, $id);
        if ($result === '0') {
            throw new \Exception("Could not acknowledge message with ID $id");
        }
    }

    /**
     * Return pending message only for this particular consumer
     * @return array
     */
    public function pending(): array
    {
        return $this->stream->pending($this->group, $this->consumer);
    }

    /**
     * Claim all given messages that have minimumt idle time of $idleTime miliseconds
     * @param array $ids
     * @param int $idleTime
     * @return array
     */
    public function claim(array $ids, int $idleTime): array
    {
        return Redis::XCLAIM($this->stream->getName(), $this->group, $this->consumer, $idleTime, $ids, 'JUSTID');
    }
}