<?php

namespace Prwnr\Streamer\Redis;

use Predis\Command\Command;
use Prwnr\Streamer\Concerns\Mergeable;

/**
 * Class ReadCommand.
 */
abstract class ReadCommand extends Command
{
    use Mergeable;

    /**
     * {@inheritdoc}
     */
    public function parseResponse($data): array
    {
        $response = [];
        foreach ((array) $data as $value) {
            if (!\is_array($value)) {
                $response[] = $value;
                continue;
            }

            $payload = null;
            foreach ($value[1] as $message) {
                $payload[$message[0]] = $this->toAssoc($message[1]);
            }
            $response[$value[0]] = $payload ?? $value[1];
        }

        return $response;
    }

    /**
     * {@inheritdoc}
     */
    protected function filterArguments(array $arguments): array
    {
        foreach ($arguments as $key => $argument) {
            if ($argument === 'STREAMS' && \is_array($arguments[$key + 1])) {
                $streams = $arguments[$key + 1];
                $ids = $arguments[$key + 2];
                unset($arguments[$key + 1], $arguments[$key + 2]);
                $arguments = array_merge($arguments, $streams, $ids);

                return $arguments;
            }
        }

        return $arguments;
    }
}
