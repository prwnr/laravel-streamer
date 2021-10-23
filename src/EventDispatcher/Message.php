<?php

namespace Prwnr\Streamer\EventDispatcher;

use Prwnr\Streamer\Concerns\HashableMessage;
use Prwnr\Streamer\Contracts\Event;

/**
 * Class Message.
 */
class Message extends StreamMessage
{
    use HashableMessage;

    /**
     * @inheritDoc
     */
    public function getData(): array
    {
        return json_decode($this->content['data'], true);
    }

    /**
     * Message constructor.
     *
     * @param  array  $meta
     * @param  array  $data
     */
    public function __construct(array $meta, array $data)
    {
        $payload = [
            '_id' => $meta['_id'] ?? '*',
            'type' => $meta['type'] ?? Event::TYPE_EVENT,
            'version' => '1.3',
            'name' => $meta['name'],
            'domain' => $meta['domain'] ?? '',
            'created' => $meta['created'] ?? time(),
            'data' => json_encode($data),
        ];

        $this->content = $payload;
        $this->hashIt();
    }
}
