<?php

namespace Prwnr\Streamer\Redis;

use Predis\Command\Command;

/**
 * Class RangeCommand.
 */
abstract class RangeCommand extends Command
{
    use Mergeable;

    /**
     * {@inheritdoc}
     */
    public function parseResponse($data): array
    {
        $response = [];
        foreach ((array) $data as $value) {
            $payload = null;
            if (\is_array($value[1])) {
                $payload = $this->toAssoc($value[1]);
            }
            $response[$value[0]] = $payload ?: $value[1];
        }

        return $response;
    }
}
