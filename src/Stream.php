<?php

declare(strict_types=1);

namespace Prwnr\Streamer;

use BadMethodCallException;
use Prwnr\Streamer\Concerns\ConnectsWithRedis;
use Prwnr\Streamer\Contracts\StreamableMessage;
use Prwnr\Streamer\Stream\Range;
use RedisException;

class Stream
{
    use ConnectsWithRedis;

    final public const STREAM = 'STREAM';
    final public const GROUPS = 'GROUPS';
    final public const CREATE = 'CREATE';
    final public const CONSUMERS = 'CONSUMERS';
    final public const NEW_ENTRIES = '$';
    final public const FROM_START = '0';

    /**
     * Stream constructor.
     */
    public function __construct(public readonly string $name)
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getNewEntriesKey(): string
    {
        return self::NEW_ENTRIES;
    }

    public function add(StreamableMessage $message, string $id = '*'): string
    {
        return (string) $this->redis()->xAdd($this->name, $id, $message->getContent());
    }

    public function delete(string $id): int
    {
        $result = $this->redis()->xDel($this->name, [$id]);
        if ($result === false) {
            return 0;
        }

        return $result;
    }

    public function read(string $from = self::FROM_START, int $limit = 0): array
    {
        if ($limit !== 0) {
            return $this->redis()->xRead([$this->name => $from], $limit);
        }

        return $this->redis()->xRead([$this->name => $from]);
    }

    public function await(string $lastSeenId = self::FROM_START, int|float $timeout = 0): ?array
    {
        return $this->redis()->xRead([$this->name => $lastSeenId], 0, $timeout);
    }

    public function readRange(Range $range, ?int $limit = null): array
    {
        $method = 'xRANGE';
        $start = $range->start;
        $stop = $range->stop;
        if ($range->direction === Range::BACKWARD) {
            $method = 'xREVRANGE';
            $start = $range->stop;
            $stop = $range->start;
        }

        if ($limit) {
            return $this->redis()->$method($this->name, $start, $stop, $limit);
        }

        return $this->redis()->$method($this->name, $start, $stop);
    }

    public function createGroup(string $name, string $from = self::FROM_START, bool $createStreamIfNotExists = true): bool
    {
        if ($createStreamIfNotExists) {
            return $this->redis()->xGroup(self::CREATE, $this->name, $name, $from, 'MKSTREAM');
        }

        return $this->redis()->xGroup(self::CREATE, $this->name, $name, $from);
    }

    /**
     * Return all pending messages from given group.
     * Optionally it can return pending message for single consumer.
     */
    public function pending(string $group, ?string $consumer = null): array
    {
        $pending = $this->redis()->xPending($this->name, $group);
        if (!$pending) {
            return [];
        }

        $pendingCount = array_shift($pending);

        if ($consumer) {
            return $this->redis()->xPending($this->name, $group, Range::FIRST, Range::LAST, $pendingCount, $consumer);
        }

        return $this->redis()->xPending($this->name, $group, Range::FIRST, Range::LAST, $pendingCount);
    }

    /**
     * @throws RedisException
     */
    public function len(): int
    {
        return $this->redis()->xLen($this->name);
    }

    /**
     * @throws RedisException
     * @throws StreamNotFoundException
     */
    public function info(): array
    {
        $result = $this->redis()->xInfo(self::STREAM, $this->name);
        if (!$result) {
            throw new StreamNotFoundException(sprintf('No results for stream %s', $this->name));
        }

        return $result;
    }

    /**
     * Returns XINFO for stream with FULL flag.
     * Available since Redis v6.0.0.
     *
     * @throws RedisException
     * @throws StreamNotFoundException
     */
    public function fullInfo(): array
    {
        $info = $this->redis()->info();
        if (!version_compare($info['redis_version'], '6.0.0', '>=')) {
            throw new BadMethodCallException('fullInfo only available for Redis 6.0 or above.');
        }

        $result = $this->redis()->xInfo(self::STREAM, $this->name, 'FULL');
        if (!$result) {
            throw new StreamNotFoundException(sprintf('No results for stream %s', $this->name));
        }

        return $result;
    }

    /**
     * @throws RedisException
     * @throws StreamNotFoundException
     */
    public function groups(): array
    {
        $result = $this->redis()->xInfo(self::GROUPS, $this->name);
        if (!$result) {
            throw new StreamNotFoundException(sprintf('No results for stream %s', $this->name));
        }

        return $result;
    }

    /**
     *
     * @throws RedisException
     * @throws StreamNotFoundException
     */
    public function consumers(string $group): array
    {
        $result = $this->redis()->xInfo(self::CONSUMERS, $this->name, $group);
        if (!$result) {
            throw new StreamNotFoundException(sprintf('No results for stream %s', $this->name));
        }

        return $result;
    }

    /**
     * @throws RedisException
     */
    public function groupExists(string $name): bool
    {
        try {
            $groups = $this->groups();
        } catch (StreamNotFoundException) {
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
