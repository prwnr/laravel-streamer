<?php

namespace Prwnr\Streamer\Commands\Archive;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Prwnr\Streamer\Stream;
use Symfony\Component\Console\Input\InputOption;

abstract class ProcessMessagesCommand extends Command
{

    public function handle(): int
    {
        $olderThan = new Carbon('-'.$this->option('older_than'));

        $streams = $this->option('streams');
        if (!$streams || !is_array($streams)) {
            $this->error('Streams option is required with at least one stream name provided.');
            return 1;
        }

        $messageCount = 0;
        foreach ($streams as $name) {
            $stream = new Stream($name);
            $messages = $stream->read();
            foreach ($messages[$name] as $id => $message) {
                if ($olderThan->lt(Carbon::createFromTimestamp($message['created']))) {
                    continue;
                }

                $this->process($name, $id, $message);
                $messageCount++;
            }
        }

        $this->info(sprintf(
            "Total of %d %s processed.",
            $messageCount,
            Str::plural('message', $messageCount)
        ));

        return 0;
    }

    /**
     * @param  string  $stream
     * @param  string  $id
     * @param  array  $message
     */
    abstract protected function process(string $stream, string $id, array $message): void;

    /**
     * @inheritDoc
     */
    protected function getOptions(): array
    {
        return [
            [
                'streams',
                null,
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                'List of streams to process.'
            ],
            [
                'older_than',
                null,
                InputOption::VALUE_REQUIRED,
                'How old messages should be to get process. The format to use this option looks like: 1 day, 1 week, 5 days, 4 weeks etc. It will take the current time and subtract the option value.'
            ],
        ];
    }
}