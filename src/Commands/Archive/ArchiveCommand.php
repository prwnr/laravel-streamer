<?php

declare(strict_types=1);

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

    public function __construct(private readonly Archiver $archiver)
    {
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function process(string $stream, string $id, array $message): void
    {
        try {
            $received = new ReceivedMessage($id, $message);

            $this->archiver->archive($received);
            $this->info(sprintf("Message [%s] has been archived from the '%s' stream.", $id, $stream));
        } catch (Exception $exception) {
            report($exception);
            $this->error(sprintf(
                "Message [%s] from the '%s' stream could not be archived. Error: ",
                $id,
                $stream
            ).$exception->getMessage());
        }
    }
}
