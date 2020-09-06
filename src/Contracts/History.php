<?php

namespace Prwnr\Streamer\Contracts;

use Carbon\Carbon;
use Prwnr\Streamer\History\Snapshot;

/**
 * Interface History
 */
interface History
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
     * @param string $event
     * @param string $identifier
     * @param Carbon|null $until
     * @return array
     */
    public function replay(string $event, string $identifier, Carbon $until = null): array;
}