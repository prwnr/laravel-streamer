<?php

namespace Prwnr\Streamer\EventDispatcher;

/**
 * Class ReceivedMessage.
 */
class ReceivedMessage extends StreamMessage
{
    /**
     * ReceivedMessage constructor.
     * @param  string  $id
     * @param  array  $content
     */
    public function __construct(string $id, array $content)
    {
        $content['_id'] = $id;
        $content['data'] = json_decode($content['data'], true);
        $this->content = $content;
    }
}
