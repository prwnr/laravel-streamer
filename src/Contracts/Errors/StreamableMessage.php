<?php

declare(strict_types=1);

namespace Prwnr\Streamer\Contracts\Errors;

interface StreamableMessage
{
    public function getContent(): array;
}
