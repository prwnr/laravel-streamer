<?php

namespace Prwnr\Streamer\Commands\FailedMessages;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Prwnr\Streamer\Contracts\Archiver;
use Prwnr\Streamer\Contracts\Errors\MessagesFailer;
use Prwnr\Streamer\Contracts\Errors\Repository;
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

    private array $specifications = [
        'id' => IdentifierSpecification::class,
        'receiver' => ReceiverSpecification::class,
        'stream' => StreamSpecification::class,
    ];

    public function __construct(
        private readonly Repository $repository,
        private readonly MessagesFailer $failer,
        private readonly Archiver $archiver
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->repository->count() === 0) {
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
     * @inheritDoc
     */
    protected function getOptions(): array
    {
        return [
            [
                'all', null, InputOption::VALUE_NONE,
                'Retries all failed messages.',
            ],
            [
                'id', null, InputOption::VALUE_REQUIRED,
                'Retries messages with given ID (messages from different streams may have same IDs and some messages may fail for multiple listeners).',
            ],
            [
                'stream', null, InputOption::VALUE_REQUIRED,
                'Retries messages from given Stream name.',
            ],
            [
                'receiver', null, InputOption::VALUE_REQUIRED,
                'Retries messages with given receiver associated with them.',
            ],
            [
                'purge', null, InputOption::VALUE_NONE,
                'Will remove message from the stream if it will be retried successfully and there will be no other failures saved.',
            ],
            [
                'archive', null, InputOption::VALUE_NONE,
                'Will remove message from the stream and store it in database if it will be retried successfully and there will be no other failures saved.',
            ],
        ];
    }

    /**
     * Retries set of messages.
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
            } catch (MessageRetryFailedException $messageRetryFailedException) {
                report($messageRetryFailedException);

                $this->error($messageRetryFailedException->getMessage());
            }
        }
    }

    private function archive(FailedMessage $message): void
    {
        if ($this->hasOtherFailures($message)) {
            return;
        }

        try {
            $receivedMessage = new ReceivedMessage($message->id, $message->getStreamMessage());
            $this->archiver->archive($receivedMessage);
            $this->info(sprintf(
                "Message [%s] has been archived from the '%s' stream.",
                $message->id,
                $message->getStreamInstance()->getName()
            ));
        } catch (Exception $exception) {
            report($exception);
            $this->warn(sprintf(
                "Message [%s] from the '%s' stream could not be archived. Error: ",
                $message->id,
                $message->getStreamInstance()->getName()
            ).$exception->getMessage());
        }
    }

    private function purge(FailedMessage $message): void
    {
        if ($this->hasOtherFailures($message)) {
            return;
        }

        $result = $message->getStreamInstance()->delete($message->id);
        if ($result !== 0) {
            $this->info(sprintf(
                "Message [%s] has been purged from the '%s' stream.",
                $message->id,
                $message->getStreamInstance()->getName()
            ));
        }
    }

    private function hasOtherFailures(FailedMessage $message): bool
    {
        return $this->getMessages([
            'id' => $message->id,
            'stream' => $message->getStreamInstance()->getName(),
        ])->isNotEmpty();
    }

    private function getMessages(array $filters): Collection
    {
        $specification = $this->prepareSpecification(array_filter($filters));

        return $this->repository->all()
            ->filter(static fn (FailedMessage $message): bool => $specification->isSatisfiedBy($message));
    }

    private function prepareSpecification(array $filters): MatchAllSpecification
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

    private function printSuccess(FailedMessage $message): void
    {
        $this->info(sprintf(
            'Successfully retried [%s] on %s stream by [%s] listener',
            $message->id,
            $message->getStreamInstance()->getName(),
            $message->receiver
        ));
    }
}
