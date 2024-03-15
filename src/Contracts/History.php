<?php

declare(strict_types=1);

namespace Prwnr\Streamer\Contracts;

use Carbon\Carbon;
use Prwnr\Streamer\History\Snapshot;

interface History
{
    /**
     * Records snapshot information in Redis.
     */
    public function record(Snapshot $snapshot): void;

    /**
     * Replays event history by its specific identifier.
     *
     */
    public function replay(string $event, string $identifier, Carbon $until = null): array;
}
