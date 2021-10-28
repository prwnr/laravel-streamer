<?php

namespace Prwnr\Streamer\Commands\Archive;

use Exception;
use Prwnr\Streamer\Stream;

class PurgeCommand extends ProcessMessagesCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'streamer:purge';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Streamer Purge command, to purge stream messages.';

    /**
     * @inheritDoc
     */
    protected function process(string $stream, string $id, array $message): void
    {
        try {
            $result = (new Stream($stream))->delete($id);
            if (!$result) {
                $this->warn("Message [$id] from the '$stream' stream could not be purged or is already deleted.");
                return;
            }

            $this->info("Message [$id] has been purged from the '$stream' stream.");
        } catch (Exception $e) {
            report($e);
            $this->warn("Message [$id] from the '$stream' stream could not be purged. Error: ".$e->getMessage());
        }
    }
}