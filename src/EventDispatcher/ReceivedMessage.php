<?php

declare(strict_types=1);

namespace Prwnr\Streamer\EventDispatcher;

use JsonException;

class ReceivedMessage extends StreamMessage
{
    /**
     * @throws JsonException
     */
    public function __construct(string $id, array $content)
    {
        $content['_id'] = $id;
        $content['data'] = json_decode((string) $content['data'], true, 512, JSON_THROW_ON_ERROR);
        $this->content = $content;
    }
}
