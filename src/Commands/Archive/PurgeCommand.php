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
            if ($result === 0) {
                $this->warn(sprintf(
                    "Message [%s] from the '%s' stream could not be purged or is already deleted.",
                    $id,
                    $stream
                ));
                return;
            }

            $this->info(sprintf("Message [%s] has been purged from the '%s' stream.", $id, $stream));
        } catch (Exception $exception) {
            report($exception);
            $this->warn(sprintf(
                "Message [%s] from the '%s' stream could not be purged. Error: ",
                $id,
                $stream
            ).$exception->getMessage());
        }
    }
}
