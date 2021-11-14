<?php

namespace Prwnr\Streamer\Commands\FailedMessages;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Prwnr\Streamer\Contracts\Archiver;
use Prwnr\Streamer\Contracts\Errors\MessagesFailer;
use Prwnr\Streamer\Contracts\Errors\Repository;
use Prwnr\Streamer\Contracts\Errors\Specification;
use Prwnr\Streamer\Errors\FailedMessage;
use Prwnr\Streamer\Errors\Specifications\IdentifierSpecification;
use Prwnr\Streamer\Errors\Specifications\MatchAllSpecification;
use Prwnr\Streamer\Errors\Specifications\ReceiverSpecification;
use Prwnr\Streamer\Errors\Specifications\StreamSpecification;
use Prwnr\Streamer\EventDispatcher\ReceivedMessage;
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

    private MessagesFailer $failer;
    private Repository $repository;
    private Archiver $archiver;
    private array $specifications = [
        'id' => IdentifierSpecification::class,
        'receiver' => ReceiverSpecification::class,
        'stream' => StreamSpecification::class,
    ];

    /**
     * RetryFailedCommand constructor.
     *
     * @param  Repository  $repository
     * @param  MessagesFailer  $failer
     * @param  Archiver  $archiver
     */
    public function __construct(Repository $repository, MessagesFailer $failer, Archiver $archiver)
    {
        parent::__construct();

        $this->failer = $failer;
        $this->repository = $repository;
        $this->archiver = $archiver;
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
        $messages = $this->getMessages($filters);
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
     * @param  Collection&FailedMessage[]  $messages
     */
    private function retry(Collection $messages): void
    {
        foreach ($messages as $message) {
            try {
                $this->failer->retry($message);
                $this->printSuccess($message);

                if ($this->option('archive')) {
                    $this->archive($message);
                    continue;
                }

                if ($this->option('purge')) {
                    $this->purge($message);
                }
            } catch (MessageRetryFailedException $e) {
                report($e);

                $this->error($e->getMessage());
            }
        }
    }

    /**
     * @param  FailedMessage  $message
     */
    private function archive(FailedMessage $message): void
    {
        if ($this->hasOtherFailures($message)) {
            return;
        }

        try {
            $receivedMessage = new ReceivedMessage($message->getId(), $message->getStreamMessage());
            $this->archiver->archive($receivedMessage);
            $this->info("Message [{$message->getId()}] has been archived from the '{$message->getStream()->getName()}' stream.");
        } catch (Exception $e) {
            report($e);
            $this->warn("Message [{$message->getId()}] from the '{$message->getStream()->getName()}' stream could not be archived. Error: ".$e->getMessage());
        }
    }

    /**
     * @param  FailedMessage  $message
     */
    private function purge(FailedMessage $message): void
    {
        if ($this->hasOtherFailures($message)) {
            return;
        }

        $result = $message->getStream()->delete($message->getId());
        if ($result) {
            $this->info("Message [{$message->getId()}] has been purged from the '{$message->getStream()->getName()}' stream.");
        }
    }

    /**
     * @param  FailedMessage  $message
     * @return bool
     */
    private function hasOtherFailures(FailedMessage $message): bool
    {
        return $this->getMessages([
            'id' => $message->getId(),
            'stream' => $message->getStream()->getName(),
        ])->isNotEmpty();
    }

    /**
     * @param  array  $filters
     * @return Collection|FailedMessage[]
     */
    private function getMessages(array $filters)
    {
        $specification = $this->prepareSpecification(array_filter($filters));

        return $this->repository->all()
            ->filter(static fn(FailedMessage $message) => $specification->isSatisfiedBy($message));
    }

    /**
     * @param  array  $filters
     * @return Specification
     */
    private function prepareSpecification(array $filters): Specification
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
            'Successfully retried [%s] on %s stream by [%s] listener',
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
                'all', null, InputOption::VALUE_NONE,
                'Retries all failed messages.'
            ],
            [
                'id', null, InputOption::VALUE_REQUIRED,
                'Retries messages with given ID (messages from different streams may have same IDs and some messages may fail for multiple listeners).'
            ],
            [
                'stream', null, InputOption::VALUE_REQUIRED,
                'Retries messages from given Stream name.'
            ],
            [
                'receiver', null, InputOption::VALUE_REQUIRED,
                'Retries messages with given receiver associated with them.'
            ],
            [
                'purge', null, InputOption::VALUE_NONE,
                'Will remove message from the stream if it will be retried successfully and there will be no other failures saved.'
            ],
            [
                'archive', null, InputOption::VALUE_NONE,
                'Will remove message from the stream and store it in database if it will be retried successfully and there will be no other failures saved.'
            ],
        ];
    }
}