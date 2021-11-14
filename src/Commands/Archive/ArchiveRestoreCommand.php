<?php

namespace Prwnr\Streamer\Commands\Archive;

use Exception;
use Illuminate\Console\Command;
use Prwnr\Streamer\Archiver\StorageManager;
use Prwnr\Streamer\Contracts\Archiver;
use Prwnr\Streamer\Contracts\ArchiveStorage;
use Prwnr\Streamer\EventDispatcher\Message;
use Symfony\Component\Console\Input\InputOption;

class ArchiveRestoreCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'streamer:archive:restore';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Streamer Archive Restore command, to restore archived messages from database storage back to Stream.';

    private Archiver $archiver;
    private ArchiveStorage $storage;

    /**
     * ArchiveRestoreCommand constructor.
     *
     * @param  Archiver  $archiver
     * @param  StorageManager  $manager
     */
    public function __construct(Archiver $archiver, StorageManager $manager)
    {
        parent::__construct();

        $this->archiver = $archiver;
        $this->storage = $manager->driver(config('streamer.archive.storage_driver'));
    }

    public function handle(): int
    {
        $confirm = $this->confirm(
            'Restoring a message will add it back onto the stream and will trigger listeners hooked to its event. Do you want to continue?',
            true
        );

        if (!$confirm) {
            return 0;
        }

        if ($this->option('all')) {
            foreach ($this->storage->all() as $message) {
                $this->restore($message);
            }

            return 0;
        }

        if ($this->option('id')) {
            if (!$this->option('stream')) {
                $this->warn('To restore by ID, a stream name needs to be provided as well.');
                return 1;
            }

            $message = $this->storage->find($this->option('stream'), $this->option('id'));
            if (!$message) {
                $this->error('The message could not be found in archive storage.');
                return 1;
            }

            $this->restore($message);
            return 0;
        }

        if ($this->option('stream')) {
            foreach ($this->storage->findMany($this->option('stream')) as $message) {
                $this->restore($message);
            }

            return 0;
        }

        $this->error('At least one option must be used to restore the message.');

        return 1;
    }

    /**
     * @param  Message  $message
     */
    private function restore(Message $message): void
    {
        try {
            $id = $this->archiver->restore($message);
            $this->info("Successfully restored [{$message->getEventName()}][{$message->getId()}] message. New ID: $id");
        } catch (Exception $e) {
            report($e);
            $this->info("Failed to restore [{$message->getEventName()}][{$message->getId()}] message. Error: {$e->getMessage()}");
        }
    }

    /**
     * @inheritDoc
     */
    protected function getOptions(): array
    {
        return [
            [
                'all', null, InputOption::VALUE_NONE,
                'Restores all archived messages back to the stream.'
            ],
            [
                'id', null, InputOption::VALUE_REQUIRED,
                'Restores archived message back to the stream by ID. Requires --stream option to be used as well.'
            ],
            [
                'stream', null, InputOption::VALUE_REQUIRED,
                'Restores all archived messages from a selected stream.'
            ],
        ];
    }
}