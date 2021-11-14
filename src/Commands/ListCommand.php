<?php

namespace Prwnr\Streamer\Commands;

use Illuminate\Console\Command;
use Prwnr\Streamer\ListenersStack;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class ListCommand
 */
class ListCommand extends Command
{
    /**
     * @var string
     */
    protected $name = 'streamer:list';

    /**
     * @var string
     */
    protected $description = 'Lists all registered events with listeners that are attached to them.';

    /**
     * @return int
     */
    public function handle(): int
    {
        $headers = ['Event'];
        $isCompact = $this->option('compact');

        if (!$isCompact) {
            $headers[] = 'Listeners';
        }

        $this->table(
            $headers,
            $this->makeRows(),
            $isCompact ? 'compact' : 'default'
        );

        return 0;
    }

    /**
     * @return array
     */
    protected function makeRows(): array
    {
        $listeners = ListenersStack::all();
        $rows = [];

        $isCompact = $this->option('compact');

        foreach ($listeners as $event => $listener) {
            if ($isCompact) {
                $rows[] = [$event];

                continue;
            }

            $eventListeners = implode(PHP_EOL, $listener);

            $rows[] = [
                $event,
                $eventListeners ?: 'none'
            ];
        }

        return $rows;
    }

    /**
     * @inheritDoc
     */
    protected function getOptions(): array
    {
        return [
            [
                'compact', null, InputOption::VALUE_NONE,
                'Returns only names of events that are registered in streamer.'
            ]
        ];
    }
}
