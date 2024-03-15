<?php

declare(strict_types=1);

namespace Prwnr\Streamer\EventDispatcher;

use JsonException;
use Prwnr\Streamer\Concerns\HashableMessage;
use Prwnr\Streamer\Contracts\Event;

class Message extends StreamMessage
{
    use HashableMessage;

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

        $this->content = $payload;
        $this->hashIt();
    }

    /**
     * @throws JsonException
     */
    public function getData(): array
    {
        return json_decode((string) $this->content['data'], true, 512, JSON_THROW_ON_ERROR);
    }
}
