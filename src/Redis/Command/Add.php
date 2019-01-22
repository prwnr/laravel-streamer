<?php

namespace Prwnr\Streamer\Redis\Command;

use Predis\Command\Command;

/**
 * @link https://redis.io/commands/xadd
 *
 * Class Add
 */
class Add extends Command
{
    /**
     * {@inheritdoc}
     */
    public function getId(): string
    {
        return 'XADD';
    }

    /**
     * {@inheritdoc}
     */
    protected function filterArguments(array $arguments): array
    {
        if (\count($arguments) === 3 && \is_array($arguments[2])) {
            $payload = array_pop($arguments);
            foreach ($payload as $name => $value) {
                $arguments[] = $name;
                $arguments[] = $value;
            }
        }

        return $arguments;
    }
}
