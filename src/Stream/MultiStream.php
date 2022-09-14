<?php

namespace Prwnr\Streamer\Stream;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Prwnr\Streamer\Concerns\ConnectsWithRedis;
use Prwnr\Streamer\Contracts\StreamableMessage;
use Prwnr\Streamer\Exceptions\AcknowledgingFailedException;
use Prwnr\Streamer\Stream;

class MultiStream
{
    use ConnectsWithRedis;

    /** @var Collection&Stream[] */
    private Collection $streams;
    private string $consumer;
    private string $group;

    /**
     * MultiStream constructor.
     *
     * @param  array  $streams
     * @param  string  $consumer
     * @param  string  $group
     */
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
     * @return string
     */
    public function getNewEntriesKey(): string
    {
        if ($this->consumer && $this->group) {
            return Consumer::NEW_ENTRIES;
        }

        return Stream::NEW_ENTRIES;
    }

    /**
     * Adds new message to Streams (if such is in MultiStream collection).
     *
     * @param  array  $streams  [stream => id] format. if ID is not a string, assuming "*"
     * @param  StreamableMessage  $message
     * @return array
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
     * Deletes message from a Stream (if such is in MultiStream collection)
     *
     * @param  array  $streams  [stream => [ids]] format
     * @return int
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

    /**
     * @param  string  $lastSeenId
     * @param  int  $timeout
     * @return array|null
     */
    public function await(string $lastSeenId = '', int $timeout = 0): ?array
    {
        if (!$lastSeenId) {
            $lastSeenId = $this->getNewEntriesKey();
        }

        $result = null;
        if ($this->streams->count() === 1) {
            return $this->parseResult($this->awaitSingle($this->streams->first(), $lastSeenId, $timeout));
        }

        if (!$result) {
            $result = $this->awaitMultiple($lastSeenId, $timeout);
        }

        if (!is_array($result) || empty($result)) {
            return [];
        }

        return $this->sortByTimestamps($this->parseResult($result));
    }

    /**
     * Acknowledges multiple messages if MultiStream has a group.
     *
     * @param  array  $streams  [stream => [ids]] format
     * @return void
     * @throws Exception
     */
    public function acknowledge(array $streams): void
    {
        if (!$this->group) {
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

        if ($notAcknowledged) {
            throw new AcknowledgingFailedException(
                "Not all messages were acknowledged. Streams affected: ".implode(', ', $notAcknowledged)
            );
        }
    }

    /**
     * @param  Stream  $stream
     * @param  string  $lastSeenId
     * @param  int  $timeout
     * @return array
     */
    private function awaitSingle(Stream $stream, string $lastSeenId, int $timeout): array
    {
        if (!$this->consumer && !$this->group) {
            return $stream->await($lastSeenId, $timeout);
        }

        $consumer = new Consumer($this->consumer, $stream, $this->group);
        if (!$lastSeenId) {
            $lastSeenId = $consumer->getNewEntriesKey();
        }

        return $consumer->await($lastSeenId, $timeout);
    }

    /**
     * @param  string  $lastSeenId
     * @param  int  $timeout
     * @return array
     */
    private function awaitMultiple(string $lastSeenId, int $timeout): array
    {
        $streams = $this->streams->map(static fn(Stream $s) => $lastSeenId)->toArray();

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

    /**
     * @param $result
     * @return array
     */
    private function parseResult($result): array
    {
        $list = [];
        foreach ($result as $stream => $messages) {
            foreach ($messages as $id => $message) {
                $list[] = [
                    'stream' => $stream,
                    'id' => $id,
                    'message' => $message
                ];
            }
        }
        return $list;
    }

    /**
     * @param  array  $list
     * @return array
     */
    private function sortByTimestamps(array $list): array
    {
        usort($list, static function ($a, $b) {
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