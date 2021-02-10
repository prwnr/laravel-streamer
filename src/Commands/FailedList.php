<?php

namespace Prwnr\Streamer\Commands;

use Illuminate\Console\Command;
use Prwnr\Streamer\Contracts\Errors\Repository;
use Prwnr\Streamer\Errors\FailedMessage;
use Symfony\Component\Console\Input\InputOption;

class FailedList extends Command
{
    /**
     * @var string
     */
    protected $name = 'streamer:failed:list';

    /**
     * @var string
     */
    protected $description = 'Lists all failed stream messages with error messages.';

    /**
     * @param  Repository  $errorsRepository
     * @return int
     */
    public function handle(Repository $errorsRepository): int
    {
        if (!$errorsRepository->count()) {
            $this->info('No failed messages');
            return 0;
        }

        $isCompact = $this->option('compact');

        $this->table(
            $this->headers(),
            $errorsRepository->all()->map(static function (FailedMessage $message) use ($isCompact) {
                $serialized = $message->jsonSerialize();
                if ($isCompact) {
                    unset($serialized['receiver'], $serialized['date']);
                }

                return $serialized;
            })->toArray()
        );

        return 0;
    }

    /**
     * @return string[]
     */
    protected function headers(): array
    {
        if ($this->option('compact')) {
            return [
                'ID',
                'Stream',
                'Error'
            ];
        }

        return [
            'ID',
            'Stream',
            'Receiver',
            'Error',
            'Date',
        ];
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
                'Returns only IDs, Stream names and Errors of failed messages.'
            ]
        ];
    }
}