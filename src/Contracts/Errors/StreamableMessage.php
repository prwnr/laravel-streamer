<?php

namespace Prwnr\Streamer\Contracts\Errors;

interface StreamableMessage
{
    public function getContent(): array;
}
