<?php

namespace Prwnr\Streamer\EventDispatcher;

use JsonException;

/**
 * Class ReceivedMessage.
 */
class ReceivedMessage extends StreamMessage
{
    /**
     * ReceivedMessage constructor.
     * @param  string  $id
     * @param  array  $content
     * @throws JsonException
     */
    public function __construct(string $id, array $content)
    {
        $content['_id'] = $id;
        $content['data'] = json_decode($content['data'], true, 512, JSON_THROW_ON_ERROR);
        $this->content = $content;
    }
}
