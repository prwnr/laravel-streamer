<?php

namespace Prwnr\Streamer\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Contracts\Container\BindingResolutionException;
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

    /**
     * @var Streamer
     */
    private $streamer;

    /**
     * @var MessagesFailer
     */
    private $failer;

    /**
     * @var null|int
     */
    private $maxAttempts;

    /**
     * ListenCommand constructor.
     *
     * @param  Streamer  $streamer
     * @param  MessagesFailer  $failer
     */
    public function __construct(Streamer $streamer, MessagesFailer $failer)
    {
        $this->streamer = $streamer;
        $this->failer = $failer;

        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @throws Throwable
     */
    public function handle(): int
    {
        $event = $this->argument('event');
        $listeners = ListenersStack::all();
        $localListeners = $listeners[$event] ?? null;
        if (!$localListeners) {
            $this->error("There are no local listeners associated with $event event in configuration.");

            return 1;
        }

        if ($this->option('last_id') !== null) {
            $this->streamer->startFrom($this->option('last_id'));
        }

        if ($this->option('group')) {
            $stream = new Stream($this->argument('event'));
            $this->setupGroupListening($stream);
        }

        $this->maxAttempts = $this->option('max-attempts');
        $this->listen($event, function (ReceivedMessage $message) use ($localListeners) {
            foreach ($localListeners as $listener) {
                $receiver = app()->make($listener);
                if (!$receiver instanceof MessageReceiver) {
                    $this->error("Listener class [{$listener}] needs to implement MessageReceiver");
                    continue;
                }

                try {
                    $receiver->handle($message);
                } catch (Throwable $e) {
                    report($e);

                    $this->printError($message, $listener, $e);
                    $this->failer->store($message, $receiver, $e);

                    continue;
                }

                $this->printInfo($message, $listener);
            }
        });

        return 0;
    }

    /**
     * @param  string  $event
     * @param  callable  $handler
     * @throws BindingResolutionException
     * @throws Throwable
     */
    private function listen(string $event, callable $handler): void
    {
        try {
            $this->streamer->listen($event, $handler);
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

            $this->listen($event, $handler);
        }
    }

    /**
     * @param  Stream  $stream
     */
    private function setupGroupListening(Stream $stream): void
    {
        if (!$stream->groupExists($this->option('group'))) {
            $stream->createGroup($this->option('group'));
            $this->info("Created new group: {$this->option('group')} on a stream: {$this->argument('event')}");
        }

        $consumer = $this->option('consumer');
        if (!$consumer) {
            $consumer = $this->option('group').'-'.time();
        }

        if ($this->option('reclaim')) {
            $this->reclaimMessages($stream, $consumer);
        }

        $this->streamer->asConsumer($consumer, $this->option('group'));
    }

    /**
     * @param  Stream  $stream
     * @param  string  $consumerName
     */
    private function reclaimMessages(Stream $stream, string $consumerName): void
    {
        $pendingMessages = $stream->pending($this->option('group'));

        $messages = [];
        foreach ($pendingMessages as $message) {
            $messages[] = $message[0];
        }

        if (!$messages) {
            return;
        }

        $consumer = new Stream\Consumer($consumerName, $stream, $this->option('group'));
        $consumer->claim($messages, $this->option('reclaim'));
    }

    /**
     * @param  ReceivedMessage  $message
     * @param  string  $listener
     */
    private function printInfo(ReceivedMessage $message, string $listener): void
    {
        $content = $message->getContent();
        $stream = $content['name'];

        $this->info("Processed message [{$message->getId()}] on '$stream' stream by [$listener] listener.");
    }

    /**
     * @param  ReceivedMessage  $message
     * @param  string  $listener
     * @param  Exception  $e
     */
    private function printError(ReceivedMessage $message, string $listener, Exception $e): void
    {
        $content = $message->getContent();
        $stream = $content['name'];

        $error = "Listener error. Failed processing message with ID {$message->getId()} on '$stream' stream by $listener. Error: {$e->getMessage()}";

        $this->error($error);
    }

    /**
     * @inheritDoc
     */
    protected function getArguments(): array
    {
        return [
            [
                'event',
                InputArgument::REQUIRED,
                'Name of an event that should be listened to'
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
        ];
    }
}
