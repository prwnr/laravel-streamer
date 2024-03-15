<?php

declare(strict_types=1);

namespace Prwnr\Streamer\Contracts;

interface Replayable
{
    /**
     * Event name. Can be any string.
     * Used to group replayable events together.
     */
    public function name(): string;

    /**
     * Returns unique identifier for the event, that makes it possible to replay it from stream.
     */
    public function getIdentifier(): string;
}
