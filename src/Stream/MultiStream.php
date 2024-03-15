<?php

declare(strict_types=1);

namespace Prwnr\Streamer\Stream;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Prwnr\Streamer\Concerns\ConnectsWithRedis;
use Prwnr\Streamer\Contracts\Errors\StreamableMessage;
use Prwnr\Streamer\Exceptions\AcknowledgingFailedException;
use Prwnr\Streamer\Stream;

class MultiStream
{
    use ConnectsWithRedis;

    /**
     * @var Collection&Stream[]
     */
    private Collection $streams;

    private string $consumer;

    private string $group;

    public function __construct(array $streams, string $group = '', string $consumer = '')
    {
        $this->streams = new Collection();

        foreach ($streams as $name) {
            if (!$name || !is_string($name)) {
                continue;
            }

            $stream = new Stream($name);
            $this->streams->put($name, $stream);

            if ($consumer && $group) {
                $stream->createGroup($group);
            }
        }

        $this->consumer = $consumer;
        $this->group = $group;
    }

    /**
     * @return Collection&Stream[]
     */
    public function streams(): Collection
    {
        return $this->streams;
    }

    /**
     * Adds new message to Streams (if such is in MultiStream collection).
     *
     * @param array $streams [stream => id] format. if ID is not a string, assuming "*"
     */
    public function add(array $streams, StreamableMessage $message): array
    {
        $added = [];
        foreach ($streams as $stream => $id) {
            if (!$this->streams->has($stream)) {
                continue;
            }

            if (!is_string($id)) {
                $id = '*';
            }

            $added[$stream] = $this->streams->get($stream)->add($message, $id);
        }

        return $added;
    }

    /**
     * Deletes message from a Stream (if such is in MultiStream collection).
     *
     * @param array $streams [stream => [ids]] format
     */
    public function delete(array $streams): int
    {
        $deleted = 0;
        foreach ($streams as $stream => $ids) {
            if (!$this->streams->has($stream)) {
                continue;
            }

            $deleted += $this->redis()->xDel($stream, Arr::wrap($ids));
        }

        return $deleted;
    }

    public function await(string $lastSeenId = '', float $timeout = 0.0): ?array
    {
        if ($lastSeenId === '' || $lastSeenId === '0') {
            $lastSeenId = $this->getNewEntriesKey();
        }

        if ($this->streams->count() === 1) {
            return $this->parseResult($this->awaitSingle($this->streams->first(), $lastSeenId, $timeout));
        }

        $result = $this->awaitMultiple($lastSeenId, $timeout);
        if ($result === []) {
            return [];
        }

        return $this->sortByTimestamps($this->parseResult($result));
    }

    public function getNewEntriesKey(): string
    {
        if ($this->consumer && $this->group) {
            return Consumer::NEW_ENTRIES;
        }

        return Stream::NEW_ENTRIES;
    }

    /**
     * Acknowledges multiple messages if MultiStream has a group.
     *
     * @param array $streams [stream => [ids]] format
     * @throws Exception
     */
    public function acknowledge(array $streams): void
    {
        if ($this->group === '' || $this->group === '0') {
            return;
        }

        $notAcknowledged = [];
        foreach ($streams as $stream => $ids) {
            if (!$this->streams->has($stream)) {
                continue;
            }

            $result = $this->redis()->xAck($stream, $this->group, Arr::wrap($ids));
            if ($result === 0) {
                $notAcknowledged[] = $stream;
            }
        }

        if ($notAcknowledged !== []) {
            throw new AcknowledgingFailedException(
                "Not all messages were acknowledged. Streams affected: " . implode(', ', $notAcknowledged)
            );
        }
    }

    private function parseResult($result): array
    {
        $list = [];
        foreach ($result as $stream => $messages) {
            foreach ($messages as $id => $message) {
                $list[] = [
                    'stream' => $stream,
                    'id' => $id,
                    'message' => $message,
                ];
            }
        }
        return $list;
    }

    private function awaitSingle(Stream $stream, string $lastSeenId, float $timeout): array
    {
        if (!$this->consumer && !$this->group) {
            return $stream->await($lastSeenId, $timeout);
        }

        $consumer = new Consumer($this->consumer, $stream, $this->group);
        if ($lastSeenId === '' || $lastSeenId === '0') {
            $lastSeenId = $consumer->getNewEntriesKey();
        }

        return $consumer->await($lastSeenId, $timeout);
    }

    private function awaitMultiple(string $lastSeenId, float $timeout): array
    {
        $streams = $this->streams->map(static fn (Stream $s): string => $lastSeenId)->toArray();

        if (!$this->consumer || !$this->group) {
            $result = $this->redis()->xRead($streams, null, $timeout);
            if (!is_array($result)) {
                return [];
            }

            return $result;
        }

        $result = $this->redis()->xReadGroup($this->group, $this->consumer, $streams, null, $timeout);
        if (!is_array($result)) {
            return [];
        }

        return $result;
    }

    private function sortByTimestamps(array $list): array
    {
        usort($list, static function ($a, $b): int {
            $aID = $a['id'] ?? null;
            $bID = $b['id'] ?? null;
            if ($aID === $bID) {
                return 0;
            }

            [$aTimestamp, $aSequence] = explode("-", $aID);
            [$bTimestamp, $bSequence] = explode("-", $bID);

            if ($aTimestamp === $bTimestamp) {
                if ($aSequence > $bSequence) {
                    return 1;
                }

                if ($aSequence < $bSequence) {
                    return -1;
                }

                return 0;
            }

            if ($aTimestamp > $bTimestamp) {
                return 1;
            }

            if ($aTimestamp < $bTimestamp) {
                return -1;
            }

            return 0;
        });

        return $list;
    }
}
