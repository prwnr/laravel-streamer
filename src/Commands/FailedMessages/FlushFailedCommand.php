<?php

declare(strict_types=1);

namespace Prwnr\Streamer\Commands\FailedMessages;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Prwnr\Streamer\Contracts\Errors\Repository;

class FlushFailedCommand extends Command
{
    /**
     * @var string
     */
    protected $name = 'streamer:failed:flush';

    /**
     * @var string
     */
    protected $description = 'Deletes all failed stream messages.';

    public function __construct(private readonly Repository $repository)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $count = $this->repository->count();
        if ($count === 0) {
            $this->info('No messages to remove.');
            return 0;
        }

        $this->repository->flush();
        $this->info(sprintf('Flushed %d %s.', $count, Str::plural('message', $count)));

        return 0;
    }
}
