<?php

namespace Prwnr\Streamer\Contracts;

/**
 * Interface StreamableMessage.
 */
interface StreamableMessage
{
    /**
     * Returns Message content.
     */
    public function getContent(): array;
}
