<?php

declare(strict_types=1);

namespace Prwnr\Streamer\Contracts;

interface StreamableMessage
{
    /**
     * Returns Message content.
     */
    public function getContent(): array;
}
