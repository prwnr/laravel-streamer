<?php

namespace Prwnr\Streamer\Redis\Command;

use Prwnr\Streamer\Redis\Mergeable;
use Predis\Command\Command;

/**
 * @link https://redis.io/commands/xclaim
 *
 * Class Claim
 * @package Prwnr\Streamer\Redis\Command
 */
class Claim extends Command
{
    use Mergeable;

    /**
     * {@inheritdoc}
     */
    public function getId(): string
    {
        return 'XCLAIM';
    }

    /**
     * {@inheritdoc}
     */
    public function parseResponse($data): array
    {
        $response = [];
        foreach ((array)$data as $value) {
            if (!\is_array($value)) {
                $response[] = $value;
                continue;
            }

            if (\is_array($value[1])) {
                $payload = $this->toAssoc($value[1]);
                $response[$value[0]] = $payload ?: $value[1];
                continue;
            }

            $response[] = $value[0];
        }

        return $response;
    }


    /**
     * {@inheritdoc}
     */
    protected function filterArguments(array $arguments): array
    {
        foreach ($arguments as $key => $argument) {
            if (\is_array($argument)) {
                unset($arguments[$key]);
                foreach ($argument as $i => $value) {
                    array_splice($arguments, $i + $key, 0, $value);
                }
            }
        }

        return $arguments;
    }
}
