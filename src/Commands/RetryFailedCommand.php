<?php

namespace Prwnr\Streamer\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Prwnr\Streamer\Contracts\Errors\MessagesFailer;
use Prwnr\Streamer\Contracts\Errors\Repository;
use Prwnr\Streamer\Contracts\Errors\Specification;
use Prwnr\Streamer\Errors\FailedMessage;
use Prwnr\Streamer\Errors\Specifications\IdentifierSpecification;
use Prwnr\Streamer\Errors\Specifications\MatchAllSpecification;
use Prwnr\Streamer\Errors\Specifications\ReceiverSpecification;
use Prwnr\Streamer\Errors\Specifications\StreamSpecification;
use Prwnr\Streamer\Exceptions\MessageRetryFailedException;
use Symfony\Component\Console\Input\InputOption;

class RetryFailedCommand extends Command
{
    /**
     * @var string
     */
    protected $name = 'streamer:failed:retry';

    /**
     * @var string
     */
    protected $description = 'Retries failed messages by passing them to their original Listeners.';

    /**
     * @var MessagesFailer
     */
    private $failer;

    /**
     * @var Repository
     */
    private $repository;

    /**
     * @var string[]
     */
    private $specifications = [
        'id' => IdentifierSpecification::class,
        'receiver' => ReceiverSpecification::class,
        'stream' => StreamSpecification::class,
    ];

    /**
     * RetryFailedCommand constructor.
     *
     * @param  Repository  $repository
     * @param  MessagesFailer  $failer
     */
    public function __construct(Repository $repository, MessagesFailer $failer)
    {
        parent::__construct();

        $this->failer = $failer;
        $this->repository = $repository;
    }

    public function handle(): int
    {
        if (!$this->repository->count()) {
            $this->info('There are no failed messages to retry.');
            return 0;
        }

        if ($this->option('all')) {
            $this->retry($this->repository->all());

            return 0;
        }

        $options = $this->options();
        if ($options['id'] || $options['stream'] || $options['receiver']) {
            return $this->retryBy(Arr::only($options, ['id', 'stream', 'receiver']));
        }

        $this->warn('No retry option has been selected');
        $this->info("Use '--all' flag or at least one of '--all', '--stream', '--receiver'");

        return 0;
    }

    /**
     * @param  array  $filters
     * @return int
     */
    protected function retryBy(array $filters): int
    {
        $specification = $this->prepareSpecification(array_filter($filters));
        $messages = $this->repository->all()->filter(static function (FailedMessage $message) use ($specification) {
            return $specification->isSatisfiedBy($message);
        });

        if ($messages->isEmpty()) {
            $this->info('There are no failed messages matching your criteria.');
            return 0;
        }

        $this->retry($messages);

        return 0;
    }

    /**
     * Retries set of messages
     *
     * @param  Collection  $messages
     */
    private function retry(Collection $messages): void
    {
        foreach ($messages as $message) {
            try {
                $this->failer->retry($message);
                $this->printSuccess($message);
            } catch (MessageRetryFailedException $e) {
                $this->error($e->getMessage());
            }
        }
    }

    /**
     * @param  array  $filters
     * @return Specification
     */
    protected function prepareSpecification(array $filters): Specification
    {
        $specifications = [];
        foreach ($filters as $filter => $value) {
            if (!isset($this->specifications[$filter])) {
                continue;
            }

            $specifications[] = new $this->specifications[$filter]($value);
        }

        return new MatchAllSpecification(...$specifications);
    }

    /**
     * @param  FailedMessage  $message
     */
    private function printSuccess(FailedMessage $message): void
    {
        $this->info(sprintf(
            'Successfully retired [%s] on %s stream by [%s] listener',
            $message->getId(),
            $message->getStream()->getName(),
            $message->getReceiver()
        ));
    }

    /**
     * @inheritDoc
     */
    protected function getOptions(): array
    {
        return [
            [
                'all',
                null,
                InputOption::VALUE_NONE,
                'Retries all failed messages.'
            ],
            [
                'id',
                null,
                InputOption::VALUE_REQUIRED,
                'Retries messages with given ID (messages from different streams may have same IDs and some messages may fail for multiple listeners).'
            ],
            [
                'stream',
                null,
                InputOption::VALUE_REQUIRED,
                'Retries messages from given Stream name.'
            ],
            [
                'receiver',
                null,
                InputOption::VALUE_REQUIRED,
                'Retries messages with given receiver associated with them.'
            ]
        ];
    }
}