<?php

namespace Prwnr\Streamer\EventDispatcher;

use JsonException;
use Prwnr\Streamer\Contracts\Event;

/**
 * Class Message.
 */
class Message extends StreamMessage
{
    /**
     * @throws JsonException
     */
    public function getData(): array
    {
        return json_decode((string) $this->content['data'], true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Message constructor.
     *
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
        ], static fn ($v): bool => $v !== null);

        $payload = $this->makeHash($payload);
        parent::__construct($payload);
    }

    /**
     * Creates a key from payload: type, name, domain and data; and makes hash out of it.
     * @throws JsonException
     */
    protected function makeHash(array $payload): array
    {
        $data = $payload['data'];
        if (is_array($payload['data']) || is_object($payload['data'])) {
            $data = json_encode($payload['data'], JSON_THROW_ON_ERROR);
        }

        $key = $payload['type'].$payload['name'].$payload['domain'].$data;
        $hash = hash('SHA256', $key);
        $payload['hash'] = $hash;

        return $payload;
    }
}
