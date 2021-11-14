<?php

namespace Prwnr\Streamer\Commands\FailedMessages;

use Illuminate\Console\Command;
use Prwnr\Streamer\Contracts\Errors\Repository;
use Prwnr\Streamer\Errors\FailedMessage;
use Symfony\Component\Console\Input\InputOption;

class ListFailedCommand extends Command
{
    /**
     * @var string
     */
    protected $name = 'streamer:failed:list';

    /**
     * @var string
     */
    protected $description = 'Lists all failed stream messages with error messages.';

    private Repository $repository;
    private array $compactHeaders = [
        'ID',
        'Stream',
        'Error',
    ];
    private array $headers = [
        'ID',
        'Stream',
        'Receiver',
        'Error',
        'Date',
    ];

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
        if (!$this->repository->count()) {
            $this->info('No failed messages');
            return 0;
        }

        $this->table(
            $this->option('compact') ? $this->compactHeaders : $this->headers,
            $this->getMessages()
        );

        return 0;
    }

    /**
     * @return array
     */
    protected function getMessages(): array
    {
        $isCompact = $this->option('compact');

        return $this->repository->all()->map(static function (FailedMessage $message) use ($isCompact) {
            $serialized = $message->jsonSerialize();
            if ($isCompact) {
                unset($serialized['receiver'], $serialized['date']);
            }

            return $serialized;
        })->toArray();
    }

    /**
     * @inheritDoc
     */
    protected function getOptions(): array
    {
        return [
            [
                'compact', null, InputOption::VALUE_NONE,
                'Returns only IDs, Stream names and Errors of failed messages.'
            ]
        ];
    }
}