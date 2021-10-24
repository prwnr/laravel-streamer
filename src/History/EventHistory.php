<?php

namespace Prwnr\Streamer\History;

use Carbon\Carbon;
use JsonException;
use Prwnr\Streamer\Concerns\ConnectsWithRedis;
use Prwnr\Streamer\Contracts\History;
use Prwnr\Streamer\Stream;

/**
 * Class EventHistory
 */
class EventHistory implements History
{
    use ConnectsWithRedis;

    /**
     * @inheritDoc
     * @throws JsonException
     */
    public function record(Snapshot $snapshot): void
    {
        $this->redis()->lPush($snapshot->getKey(), json_encode($snapshot->toArray(), JSON_THROW_ON_ERROR));
    }

    /**
     * @inheritDoc
     * @throws JsonException
     */
    public function replay(string $event, string $identifier, Carbon $until = null): array
    {
        $key = $event.Snapshot::KEY_SEPARATOR.$identifier;
        $snapshots = $this->redis()->lRange($key, 0, $this->redis()->lLen($key));
        $snapshotsCount = count($snapshots) - 1;
        $last = json_decode($snapshots[0], true, 512, JSON_THROW_ON_ERROR)['id'];
        $first = json_decode($snapshots[$snapshotsCount], true, 512, JSON_THROW_ON_ERROR)['id'];

        $stream = new Stream($event);
        $range = $stream->readRange(new Stream\Range($first, $last));

        $result = [];
        for ($i = $snapshotsCount; $i >= 0; $i--) {
            $snapshot = json_decode($snapshots[$i], true, 512, JSON_THROW_ON_ERROR);
            if ($until && $until <= Carbon::createFromFormat('Y-m-d H:i:s', $snapshot['date'])) {
                return $result;
            }

            $message = $range[$snapshot['id']] ?? null;
            if (!$message) {
                continue;
            }

            $record = json_decode($message['data'], true, 512, JSON_THROW_ON_ERROR);
            foreach ($record as $field => $value) {
                $result[$field] = $value;
            }
        }

        return $result;
    }
}