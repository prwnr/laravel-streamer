<?php

namespace Prwnr\Streamer;

use Prwnr\Streamer\Contracts\StreamableMessage;
use Prwnr\Streamer\Contracts\Waitable;
use Prwnr\Streamer\Stream\Range;
use Predis\Response\ServerException;

/**
 * Class Stream
 * @package Prwnr\Streamer
 */
class Stream implements Waitable
{
    use ConnectsWithRedis;

    public const COUNT = 'COUNT';
    public const STREAM = 'STREAM';
    public const STREAMS = 'STREAMS';
    public const GROUP = 'GROUP';
    public const GROUPS = 'GROUPS';
    public const CREATE = 'CREATE';
    public const CONSUMERS = 'CONSUMERS';
    public const BLOCK = 'BLOCK';
    public const MAXLEN = 'MAXLEN';
    public const NEW_ENTRIES = '$';
    public const FROM_START = '0';

    /**
     * @var string
     */
    private $name;

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getNewEntriesKey(): string
    {
        return self::NEW_ENTRIES;
    }

    /**
     * Stream constructor.
     * @param string $name
     */
    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * @param StreamableMessage $message
     * @param string $id
     * @return mixed
     */
    public function add(StreamableMessage $message, string $id = '*')
    {
        return $this->redis()->XADD($this->name, $id, $message->getContent());
    }

    /**
     * @param string $id
     * @return mixed
     */
    public function delete(string $id)
    {
        return $this->redis()->XDEL($this->name, $id);
    }

    /**
     * @param string $from
     * @param int|null $limit
     * @return array
     */
    public function read(string $from = self::FROM_START, ?int $limit = null): array
    {
        if ($limit) {
            return $this->redis()->XREAD(self::COUNT, $limit, self::STREAMS, $this->name, $from);
        }

        return $this->redis()->XREAD(self::STREAMS, $this->name, $from);
    }

    /**
     * {@inheritdoc}
     */
    public function await(string $lastId = self::FROM_START, int $timeout = 0): ?array
    {
        return $this->redis()->XREAD(self::BLOCK, $timeout, self::STREAMS, $this->name, $lastId);
    }

    /**
     * @param string $id
     */
    public function acknowledge(string $id): void
    {
        // When listening on Stream without a group we are not acknowledging any messages
    }

    /**
     * @param Range $range
     * @param int|null $limit
     * @return array
     */
    public function readRange(Range $range, ?int $limit = null): array
    {
        $method = 'XRANGE';
        $start = $range->getStart();
        $stop = $range->getStop();
        if ($range->getDirection() === Range::BACKWARD) {
            $method = 'XREVRANGE';
            $start = $range->getStop();
            $stop = $range->getStart();
        }

        if ($limit) {
            return $this->redis()->$method($this->name, $start, $stop, self::COUNT, $limit);
        }

        return $this->redis()->$method($this->name, $start, $stop);
    }

    /**
     * @param string $name
     * @param string $from
     * @param bool $createStreamIfNotExists
     */
    public function createGroup(string $name, string $from = self::FROM_START, bool $createStreamIfNotExists = true): void
    {
        if ($createStreamIfNotExists) {
            $this->redis()->XGROUP(self::CREATE, $this->name, $name, $from, 'MKSTREAM');
            return;
        }

        $this->redis()->XGROUP(self::CREATE, $this->name, $name, $from);
    }

    /**
     * Return all pending messages from given group.
     * Optionally it can return pending message for single consumer
     * @param string $group
     * @param null|string $consumer
     * @return array
     */
    public function pending(string $group, ?string $consumer = null): array
    {
        $pending = $this->redis()->XPENDING($this->name, $group);
        $pendingCount = array_shift($pending);

        if ($consumer) {
            return $this->redis()->XPENDING($this->name, $group, Range::FIRST, Range::LAST, $pendingCount, $consumer);
        }

        return $this->redis()->XPENDING($this->name, $group, Range::FIRST, Range::LAST, $pendingCount);
    }

    /**
     * @return int
     */
    public function len(): int
    {
        return $this->redis()->XLEN($this->name);
    }

    /**
     * @return array
     * @throws ServerException
     * @throws StreamNotFoundException
     */
    public function info(): array
    {
        try {
            return $this->redis()->XINFO(self::STREAM, $this->name);
        } catch (ServerException $ex) {
            if (str_contains($ex->getMessage(), 'ERR no such key')) {
                throw new StreamNotFoundException("No results for stream $this->name");
            }
            throw $ex;
        }
    }

    /**
     * @return array
     * @throws ServerException
     * @throws StreamNotFoundException
     */
    public function groups(): array
    {
        try {
            return $this->redis()->XINFO(self::GROUPS, $this->name);
        } catch (ServerException $ex) {
            if (str_contains($ex->getMessage(), 'ERR no such key')) {
                throw new StreamNotFoundException("No results for stream $this->name");
            }
            throw $ex;
        }
    }

    /**
     * @param string $group
     * @return array
     * @throws ServerException
     * @throws StreamNotFoundException
     */
    public function consumers(string $group): array
    {
        try {
            return $this->redis()->XINFO(self::CONSUMERS, $this->name, $group);
        } catch (ServerException $ex) {
            if (str_contains($ex->getMessage(), 'ERR no such key')) {
                throw new StreamNotFoundException("No results for stream $this->name");
            }
            throw $ex;
        }
    }

    /**
     * @param string $name
     * @return bool
     * @throws ServerException
     */
    public function groupExists(string $name): bool
    {
        try {
            $groups = $this->groups();
        } catch (StreamNotFoundException $ex) {
            return false;
        }

        foreach ($groups as $group) {
            if ($group['name'] === $name) {
                return true;
            }
        }

        return false;
    }

}