<?php

namespace Prwnr\Streamer\EventDispatcher;

use JsonException;
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
     * @throws JsonException
     */
    public function getData(): array
    {
        return json_decode($this->content['data'], true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Message constructor.
     *
     * @param  array  $meta
     * @param  array  $data
     * @throws JsonException
     */
    public function __construct(array $meta, array $data)
    {
        $payload = array_filter([
            '_id' => $meta['_id'] ?? '*',
            'original_id' => $meta['original_id'] ?? null,
            'type' => $meta['type'] ?? Event::TYPE_EVENT,
            'version' => '1.3',
            'name' => $meta['name'],
            'domain' => $meta['domain'] ?? '',
            'created' => $meta['created'] ?? time(),
            'data' => json_encode($data, JSON_THROW_ON_ERROR),
        ], static function ($v) {
            return $v !== null;
        });

        $this->content = $payload;
        $this->hashIt();
    }
}
