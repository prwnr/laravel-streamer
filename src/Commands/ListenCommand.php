<?php

namespace Prwnr\Streamer\Commands;

use Exception;
use Illuminate\Container\Container;
use Predis\Response\ServerException;
use Prwnr\Streamer\Contracts\MessageReceiver;
use Prwnr\Streamer\ListenersStack;
use Prwnr\Streamer\Stream;
use Illuminate\Console\Command;
use Prwnr\Streamer\EventDispatcher\Streamer;
use Prwnr\Streamer\EventDispatcher\ReceivedMessage;

/**
 * Class ListenCommand.
 */
class ListenCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'streamer:listen
                            {event : Name of an event that should be listened to}
                            {--group= : Name of your streaming group. Only when group is provided listener will listen on group as consumer}
                            {--consumer= : Name of your group consumer. If not provided a name will be created as groupname-timestamp}
                            {--reclaim= : Milliseconds of pending messages idle time, that should be reclaimed for current consumer in this group. Can be only used with group listening}
                            {--last_id= : ID from which listener should start reading messages}';

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

    public function __construct(Streamer $streamer)
    {
        $this->streamer = $streamer;
        $this->streamer->setConsole($this);

        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @throws Exception
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

        $container = Container::getInstance();
        $this->streamer->listen($event, function (ReceivedMessage $message) use ($localListeners, $container) {
            foreach ($localListeners as $listener) {
                $receiver = $container->make($listener);
                if (!$receiver instanceof MessageReceiver) {
                    $this->error("Listener class ({$listener}) needs to implement MessageReceiver");
                    continue;
                }

                $receiver->handle($message);
            }
        });

        return 0;
    }

    /**
     * @param  Stream  $stream
     *
     * @throws ServerException
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
}
