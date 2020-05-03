<?php

namespace Prwnr\Streamer\History;

use Prwnr\Streamer\Concerns\ConnectsWithRedis;
use Prwnr\Streamer\Contracts\Replayer;
use Prwnr\Streamer\Stream;

/**
 * Class Recorder
 */
class Recorder implements Replayer
{
    use ConnectsWithRedis;

    /**
     * @inheritDoc
     */
    public function record(Snapshot $snapshot): void
    {
        $this->redis()->lPush($snapshot->getKey(), json_encode($snapshot->toArray()));
    }

    /**
     * @inheritDoc
     */
    public function replay(string $event, string $identifier): array
    {
        $key = $event.'-'.$identifier;
        $snapshots = $this->redis()->lRange($key, 0, $this->redis()->lLen($key));
        $snapshotsCount = count($snapshots) - 1;
        $last = json_decode($snapshots[0], true)['id'];
        $first = json_decode($snapshots[$snapshotsCount], true)['id'];

        $stream = new Stream($event);
        $range = $stream->readRange(new Stream\Range($first, $last));

        $result = [];
        for ($i = $snapshotsCount; $i >= 0; $i--) {
            $snapshot = json_decode($snapshots[$i], true);
            $record = json_decode($range[$snapshot['id']]['data'], true);

            foreach ($record as $field => $value) {
                $result[$field] = $value;
            }
        }

        return $result;
    }
}