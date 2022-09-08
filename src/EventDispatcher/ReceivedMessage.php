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
     *
     * @throws JsonException
     */
    public function __construct(string $id, array $content)
    {
        $content['_id'] = $id;
        $content['data'] = json_decode((string) $content['data'], true, 512, JSON_THROW_ON_ERROR);

        parent::__construct($content);
    }
}
