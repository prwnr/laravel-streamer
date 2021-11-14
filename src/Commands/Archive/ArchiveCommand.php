<?php

namespace Prwnr\Streamer\Commands\Archive;

use Exception;
use Prwnr\Streamer\Contracts\Archiver;
use Prwnr\Streamer\EventDispatcher\ReceivedMessage;

class ArchiveCommand extends ProcessMessagesCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'streamer:archive';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Streamer Archive command, to archive stream messages, by removing them from stream and storing in other database storage.';

    private Archiver $archiver;

    /**
     * ArchiveCommand constructor.
     *
     * @param  Archiver  $archiver
     */
    public function __construct(Archiver $archiver)
    {
        parent::__construct();

        $this->archiver = $archiver;
    }

    /**
     * @inheritDoc
     */
    protected function process(string $stream, string $id, array $message): void
    {
        try {
            $received = new ReceivedMessage($id, $message);

            $this->archiver->archive($received);
            $this->info("Message [$id] has been archived from the '$stream' stream.");
        } catch (Exception $e) {
            report($e);
            $this->error("Message [$id] from the '$stream' stream could not be archived. Error: ".$e->getMessage());
        }
    }
}