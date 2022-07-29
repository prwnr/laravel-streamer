<?php

namespace Prwnr\Streamer\Commands;

use Exception;
use Illuminate\Console\Command;
use Prwnr\Streamer\Contracts\Archiver;
use Prwnr\Streamer\Contracts\Errors\MessagesFailer;
use Prwnr\Streamer\Contracts\MessageReceiver;
use Prwnr\Streamer\EventDispatcher\ReceivedMessage;
use Prwnr\Streamer\EventDispatcher\Streamer;
use Prwnr\Streamer\ListenersStack;
use Prwnr\Streamer\Stream;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Throwable;

/**
 * Class ListenCommand.
 */
class ListenCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'streamer:listen';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'RedisStream listen command that awaits for new messages on given Stream and fires local events based on streamer configuration';

    private Streamer $streamer;
    private MessagesFailer $failer;
    private ?int $maxAttempts;
    private Archiver $archiver;

    /**
     * ListenCommand constructor.
     *
     * @param  Streamer  $streamer
     * @param  MessagesFailer  $failer
     * @param  Archiver  $archiver
     */
    public function __construct(Streamer $streamer, MessagesFailer $failer, Archiver $archiver)
    {
        $this->streamer = $streamer;
        $this->failer = $failer;
        $this->archiver = $archiver;

        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @throws Throwable
     */
    public function handle(): int
    {
        $events = $this->getEventsToListen();
        $missingListeners = 0;
        foreach ($events as $event) {
            if (ListenersStack::hasListener($event)) {
                continue;
            }

            $this->warn("There are no local listeners associated with '$event' event in configuration.");
            $missingListeners++;
        }

        if (count($events) === $missingListeners) {
            return 1;
        }

        if ($this->option('last_id') !== null) {
            if (count($events) > 1) {
                $this->info('The last_id value will be used for all listened events.');
            }
            $this->streamer->startFrom($this->option('last_id'));
        }

        if ($this->option('group')) {
            $this->setupGroupListening();
        }

        $this->maxAttempts = $this->option('max-attempts');
        $this->listen($events, function (ReceivedMessage $message) {
            $failed = false;
            foreach (ListenersStack::getListenersFor($message->getEventName()) as $listener) {
                $receiver = app()->make($listener);
                if (!$receiver instanceof MessageReceiver) {
                    $this->error("Listener class [$listener] needs to implement MessageReceiver");
                    continue;
                }

                try {
                    $receiver->handle($message);
                } catch (Throwable $e) {
                    $failed = true;
                    report($e);

                    $this->printError($message, $listener, $e);
                    $this->failer->store($message, $receiver, $e);

                    continue;
                }

                $this->printInfo($message, $listener);
            }

            if ($failed) {
                return;
            }

            if ($this->option('archive')) {
                $this->archive($message);
            }

            if (!$this->option('archive') && $this->option('purge')) {
                $this->purge($message);
            }
        });

        return 0;
    }

    /**
     * @param  array  $events
     * @param  callable  $handler
     * @return void
     * @throws Throwable
     */
    private function listen(array $events, callable $handler): void
    {
        try {
            $this->streamer->listen($events, $handler);
        } catch (Throwable $e) {
            if (!$this->option('keep-alive')) {
                throw $e;
            }

            $this->error($e->getMessage());
            report($e);

            if ($this->maxAttempts === 0) {
                return;
            }

            $this->warn('Starting listener again due to unexpected error.');
            if ($this->maxAttempts !== null) {
                $this->warn("Attempts left: $this->maxAttempts");
                $this->maxAttempts--;
            }

            $this->listen($events, $handler);
        }
    }

    /**
     * @return void
     */
    private function setupGroupListening(): void
    {
        $multiStream = new Stream\MultiStream($this->getEventsToListen(), $this->option('group'));
        $consumer = $this->option('consumer');
        if (!$consumer) {
            $consumer = $this->option('group').'-'.time();
        }

        if ($this->option('reclaim')) {
            if ($multiStream->streams()->count() > 1) {
                $this->info('Reclaiming will reclaim pending messages on all listened events.');
            }
            $this->reclaimMessages($multiStream, $consumer);
        }

        $this->streamer->asConsumer($consumer, $this->option('group'));
    }

    /**
     * @param  Stream\MultiStream  $multiStream
     * @param  string  $consumerName
     * @return void
     */
    private function reclaimMessages(Stream\MultiStream $multiStream, string $consumerName): void
    {
        foreach ($multiStream->streams() as $stream) {
            $pendingMessages = $stream->pending($this->option('group'));
            $messages = array_map(static fn($message) => $message[0], $pendingMessages);
            if (!$messages) {
                continue;
            }

            $consumer = new Stream\Consumer($consumerName, $stream, $this->option('group'));
            $consumer->claim($messages, $this->option('reclaim'));
        }
    }

    /**
     * Removes message from the stream and stores message in DB.
     * No verification for other consumers is made in this archiving option.
     *
     * @param  ReceivedMessage  $message
     */
    private function archive(ReceivedMessage $message): void
    {
        try {
            $this->archiver->archive($message);
            $this->info("Message [{$message->getId()}] has been archived from the '{$message->getEventName()}' stream.");
        } catch (Exception $e) {
            $this->warn("Message [{$message->getId()}] from the '{$message->getEventName()}' stream could not be archived. Error: ".$e->getMessage());
        }
    }

    /**
     * Removes message from the stream.
     * No verification for other consumers is made in this purging option.
     *
     * @param  ReceivedMessage  $message
     */
    private function purge(ReceivedMessage $message): void
    {
        $stream = new Stream($message->getEventName());
        $result = $stream->delete($message->getId());
        if ($result) {
            $this->info("Message [{$message->getId()}] has been purged from the '{$message->getEventName()}' stream.");
        }
    }

    /**
     * @return array
     */
    private function getEventsToListen(): array
    {
        if ($this->option('all')) {
            return array_keys(ListenersStack::all());
        }

        $events = $this->argument('events');
        if (!$events) {
            $this->error('Either "events" argument with list of events or "--all" option is required to start listening.');
        }

        return explode(',', $events);
    }

    /**
     * @param  ReceivedMessage  $message
     * @param  string  $listener
     */
    private function printInfo(ReceivedMessage $message, string $listener): void
    {
        $this->info(sprintf(
            "Processed message [%s] on '%s' stream by [%s] listener.",
            $message->getId(),
            $message->getEventName(),
            $listener
        ));
    }

    /**
     * @param  ReceivedMessage  $message
     * @param  string  $listener
     * @param  Exception  $e
     */
    private function printError(ReceivedMessage $message, string $listener, Exception $e): void
    {
        $this->error(sprintf(
            "Listener error. Failed processing message with ID %s on '%s' stream by %s. Error: %s",
            $message->getId(),
            $message->getEventName(),
            $listener,
            $e->getMessage()
        ));
    }

    /**
     * @inheritDoc
     */
    protected function getArguments(): array
    {
        return [
            [
                'events',
                InputArgument::OPTIONAL,
                'Name (or names separated by comma) of an event(s) that should be listened to'
            ],
        ];
    }

    /**
     * @inheritDoc
     */
    protected function getOptions(): array
    {
        return [
            [
                'all', null, InputOption::VALUE_NONE,
                'Will start listening to all events that are registered in Listeners Stack. Will override usage of "events" argument'
            ],
            [
                'group', null, InputOption::VALUE_REQUIRED,
                'Name of your streaming group. Only when group is provided listener will listen on group as consumer'
            ],
            [
                'consumer', null, InputOption::VALUE_REQUIRED,
                'Name of your group consumer. If not provided a name will be created as groupname-timestamp'
            ],
            [
                'reclaim', null, InputOption::VALUE_REQUIRED,
                'Milliseconds of pending messages idle time, that should be reclaimed for current consumer in this group. Can be only used with group listening'
            ],
            [
                'last_id', null, InputOption::VALUE_REQUIRED,
                'ID from which listener should start reading messages'
            ],
            [
                'keep-alive', null, InputOption::VALUE_NONE,
                'Will keep listener alive when any unexpected non-listener related error will occur by simply restarting listening.'
            ],
            [
                'max-attempts', null, InputOption::VALUE_REQUIRED,
                'Number of maximum attempts to restart a listener on an unexpected non-listener related error'
            ],
            [
                'purge', null, InputOption::VALUE_NONE,
                'Will remove message from the stream if it will be processed successfully by all listeners in the current stack.'
            ],
            [
                'archive', null, InputOption::VALUE_NONE,
                'Will remove message from the stream and store it in database if it will be processed successfully by all listeners in the current stack.'
            ],
        ];
    }
}
