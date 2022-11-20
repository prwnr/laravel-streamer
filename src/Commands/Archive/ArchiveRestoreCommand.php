<?php

declare(strict_types=1);

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

    private readonly ArchiveStorage $storage;

    /**
     * ArchiveRestoreCommand constructor.
     */
    public function __construct(private readonly Archiver $archiver, StorageManager $manager)
    {
        parent::__construct();
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
            if (!$message instanceof Message) {
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
     * @inheritDoc
     */
    protected function getOptions(): array
    {
        return [
            [
                'all', null, InputOption::VALUE_NONE,
                'Restores all archived messages back to the stream.',
            ],
            [
                'id', null, InputOption::VALUE_REQUIRED,
                'Restores archived message back to the stream by ID. Requires --stream option to be used as well.',
            ],
            [
                'stream', null, InputOption::VALUE_REQUIRED,
                'Restores all archived messages from a selected stream.',
            ],
        ];
    }

    private function restore(Message $message): void
    {
        try {
            $id = $this->archiver->restore($message);
            $this->info(sprintf(
                'Successfully restored [%s][%s] message. New ID: %s',
                $message->getEventName(),
                $message->getId(),
                $id
            ));
        } catch (Exception $exception) {
            report($exception);
            $this->info(sprintf(
                'Failed to restore [%s][%s] message. Error: %s',
                $message->getEventName(),
                $message->getId(),
                $exception->getMessage()
            ));
        }
    }
}
