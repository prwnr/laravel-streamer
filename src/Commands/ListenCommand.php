<?php

namespace Prwnr\Streamer\Commands;

use Illuminate\Console\Command;
use Prwnr\Streamer\EventDispatcher\ReceivedMessage;
use Prwnr\Streamer\EventDispatcher\Streamer;
use Prwnr\Streamer\Stream;

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
                            {--reclaim= : Miliseconds of pending messages idle time, that should be reclaimed for current consumer in this group. Can be only used with group listening}
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

    /**
     * Execute the console command.
     *
     * @throws \Exception
     */
    public function handle(): int
    {
        $event = $this->argument('event');
        $events = config('streamer.listen_and_fire');
        $localEvents = $events[$event] ?? null;
        if (!$localEvents) {
            $this->error("There are no local events associated with $event event in configuration.");

            return 1;
        }

        $this->streamer = new Streamer();
        if (!\is_null($this->option('last_id'))) {
            $this->streamer->startFrom($this->option('last_id'));
        }

        if ($this->option('group')) {
            $stream = new Stream($this->argument('event'));
            $this->setupGroupListening($stream);
        }

        $this->streamer->listen($event, function (ReceivedMessage $message) use ($localEvents) {
            foreach ($localEvents as $localEvent) {
                event($localEvent, new $localEvent($message));
            }
        });

        return 0;
    }

    /**
     * @param Stream $stream
     *
     * @throws \Predis\Response\ServerException
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
     * @param Stream $stream
     * @param string $consumerName
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

        $consumerName = new Stream\Consumer($consumerName, $stream, $this->option('group'));
        $consumerName->claim($messages, $this->option('reclaim'));
    }
}
