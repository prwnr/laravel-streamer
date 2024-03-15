<?php

namespace Prwnr\Streamer;

use BadMethodCallException;
use Prwnr\Streamer\Concerns\ConnectsWithRedis;
use Prwnr\Streamer\Contracts\Errors\StreamableMessage;
use Prwnr\Streamer\Stream\Range;

class Stream
{
    use ConnectsWithRedis;

    public const STREAM = 'STREAM';

    public const GROUPS = 'GROUPS';

    public const CREATE = 'CREATE';

    public const CONSUMERS = 'CONSUMERS';

    public const NEW_ENTRIES = '$';

    public const FROM_START = '0';

    public function __construct(private readonly string $name)
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
        return $this->redis()->xAdd($this->name, $id, $message->getContent());
    }

    public function delete(string $id): int
    {
        return $this->redis()->xDel($this->name, [$id]);
    }

    public function read(string $from = self::FROM_START, int $limit = 0): array
    {
        $result = [];
        if ($limit !== 0) {
            $result = $this->redis()->xRead([$this->name => $from], $limit);
        }

        if ($limit === 0) {
            $result = $this->redis()->xRead([$this->name => $from]);
        }

        if (!is_array($result)) {
            return [];
        }

        return $result;
    }

    public function await(string $lastSeenId = self::FROM_START, int $timeout = 0): array
    {
        $result = $this->redis()->xRead([$this->name => $lastSeenId], 0, $timeout);
        if (!is_array($result)) {
            return [];
        }

        return $result;
    }

    public function acknowledge(string $id): void
    {
        // When listening on Stream without a group we are not acknowledging any messages
    }

    public function readRange(Range $range, ?int $limit = null): array
    {
        $method = 'xRANGE';
        $start = $range->getStart();
        $stop = $range->getStop();
        if ($range->getDirection() === Range::BACKWARD) {
            $method = 'xREVRANGE';
            $start = $range->getStop();
            $stop = $range->getStart();
        }

        $result = [];
        if ($limit) {
            $result = $this->redis()->$method($this->name, $start, $stop, $limit);
        }

        if (!$limit) {
            $result = $this->redis()->$method($this->name, $start, $stop);
        }

        if (!is_array($result)) {
            return [];
        }

        return $result;
    }

    public function createGroup(
        string $name,
        string $from = self::FROM_START,
        bool $createStreamIfNotExists = true
    ): bool {
        if ($createStreamIfNotExists) {
            return $this->redis()->xGroup(self::CREATE, $this->name, $name, $from, 'MKSTREAM');
        }

        return $this->redis()->xGroup(self::CREATE, $this->name, $name, $from);
    }

    /**
     * Return all pending messages from given group.
     * Optionally it can return pending message for single consumer.
     *
     *
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

    public function len(): int
    {
        return $this->redis()->xLen($this->name);
    }

    /**
     * Returns XINFO for stream with FULL flag.
     * Available since Redis v6.0.0.
     *
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
            throw new StreamNotFoundException("No results for stream $this->name");
        }

        return $result;
    }

    /**
     * @throws StreamNotFoundException
     */
    public function info(): array
    {
        $result = $this->redis()->xInfo(self::STREAM, $this->name);
        if (!$result) {
            throw new StreamNotFoundException("No results for stream $this->name");
        }

        return [
            'length' => null,
            'radix-tree-keys' => null,
            'radix-tree-nodes' => null,
            'last-generated-id' => null,
            'max-deleted-entry-id' => null,
            'entries-added' => null,
            'recorded-first-entry-id' => null,
            'groups' => null,
            'first-entry' => null,
            'last-entry' => null,
            ...$result,
        ];
    }

    /**
     *
     * @throws StreamNotFoundException
     *
     */
    public function consumers(string $group): array
    {
        $result = $this->redis()->xInfo(self::CONSUMERS, $this->name, $group);
        if (!$result) {
            throw new StreamNotFoundException("No results for stream $this->name");
        }

        return $result;
    }

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

    /**
     * @throws StreamNotFoundException
     *
     */
    public function groups(): array
    {
        $result = $this->redis()->xInfo(self::GROUPS, $this->name);
        if (!$result) {
            throw new StreamNotFoundException("No results for stream $this->name");
        }

        return $result;
    }
}
