<?php

namespace Prwnr\Streamer\Contracts;

interface StreamableMessage
{
    /**
     * Returns Message content.
     */
    public function getContent(): array;
}
