<?php

namespace Prwnr\Streamer\Contracts;

use Prwnr\Streamer\History\Snapshot;

/**
 * Interface Replayer
 */
interface Replayer
{
    /**
     * Records snapshot information in Redis.
     *
     * @param  Snapshot  $snapshot
     */
    public function record(Snapshot $snapshot): void;

    /**
     * Replays event history by its specific identifier.
     *
     * @param  string  $event
     * @param  string  $identifier
     * @return array
     */
    public function replay(string $event, string $identifier): array;
}