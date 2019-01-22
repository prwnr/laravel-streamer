<?php

namespace Prwnr\Streamer\Contracts;

/**
 * Interface StreamableMessage.
 */
interface StreamableMessage
{
    /**
     * @return array
     */
    public function getContent(): array;
}
