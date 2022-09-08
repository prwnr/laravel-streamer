<?php

namespace Prwnr\Streamer;

use Prwnr\Streamer\Concerns\ConnectsWithRedis;
use Prwnr\Streamer\Contracts\StreamableMessage;

/**
 * Class Streams.
 */
class Streams
{
    use ConnectsWithRedis;

    /**
     * Streams constructor.
     *
     * @param  array<int, string>  $streams
     */
    public function __construct(private readonly array $streams)
    {
    }

    /**
     * @return array<int, string>
     */
    public function add(StreamableMessage $message, string $id = '*'): array
    {
        $ids = [];
        foreach ($this->streams as $stream) {
            $ids[] = $this->redis()->XADD($stream, $id, $message->getContent());
        }

        return $ids;
    }

    /**
     * @return array<int, string>
     */
    public function read(array $from = [], ?int $limit = null): array
    {
        $read = [];
        foreach ($this->streams as $key => $stream) {
            $read[$stream] = $from[$key] ?? Stream::FROM_START;
        }

        if ($limit) {
            return $this->redis()->xRead($read, $limit);
        }

        return $this->redis()->xRead($read);
    }
}
