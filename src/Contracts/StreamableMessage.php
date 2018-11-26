<?php

namespace Prwnr\Streamer\Contracts;

/**
 * Interface StreamableMessage
 * @package Prwnr\Streamer\Contracts
 */
interface StreamableMessage
{

    /**
     * @return array
     */
    public function getContent(): array;
}