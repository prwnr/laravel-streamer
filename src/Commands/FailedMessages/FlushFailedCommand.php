<?php

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

    private Repository $repository;

    /**
     * FailedListCommand constructor.
     *
     * @param  Repository  $errorsRepository
     */
    public function __construct(Repository $errorsRepository)
    {
        parent::__construct();

        $this->repository = $errorsRepository;
    }

    /**
     * @return int
     */
    public function handle(): int
    {
        $count = $this->repository->count();
        if (!$count) {
            $this->info('No messages to remove.');
            return 0;
        }

        $this->repository->flush();
        $this->info(sprintf('Flushed %d %s.', $count, Str::plural('message', $count)));

        return 0;
    }
}