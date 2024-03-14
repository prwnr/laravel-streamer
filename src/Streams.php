<?php

namespace Prwnr\Streamer;

use Prwnr\Streamer\Concerns\ConnectsWithRedis;
use Prwnr\Streamer\Contracts\Errors\StreamableMessage;

class Streams
{
    use ConnectsWithRedis;

    public function __construct(private readonly array $streams)
    {
    }

    public function add(StreamableMessage $message, string $id = '*'): array
    {
        $ids = [];
        foreach ($this->streams as $stream) {
            $ids[] = $this->redis()->XADD($stream, $id, $message->getContent());
        }

        return $ids;
    }

    public function read(array $from = [], ?int $limit = null): array
    {
        $read = [];
        foreach ($this->streams as $key => $stream) {
            $read[$stream] = $from[$key] ?? Stream::FROM_START;
        }

        $result = [];
        if ($limit) {
            $result = $this->redis()->xRead($read, $limit);
        }

        if (!$limit) {
            $result = $this->redis()->xRead($read);
        }

        if (!is_array($result)) {
            return [];
        }

        return $result;
    }
}
