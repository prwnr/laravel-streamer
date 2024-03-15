<?php

declare(strict_types=1);

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

    public function __construct(private readonly Repository $repository)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->repository->count() === 0) {
            $this->info('No failed messages');
            return 0;
        }

        $this->table(
            $this->option('compact') ? $this->compactHeaders : $this->headers,
            $this->getMessages()
        );

        return 0;
    }

    protected function getMessages(): array
    {
        $isCompact = $this->option('compact');

        return $this->repository->all()->map(static function (FailedMessage $message) use ($isCompact): array {
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
                'compact',
                null,
                InputOption::VALUE_NONE,
                'Returns only IDs, Stream names and Errors of failed messages.',
            ],
        ];
    }
}
