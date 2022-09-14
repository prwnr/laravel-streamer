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

    private array $streams;

    /**
     * Streams constructor.
     *
     * @param array $streams
     */
    public function __construct(array $streams)
    {
        $this->streams = $streams;
    }

    /**
     * @param  StreamableMessage  $message
     * @param  string  $id
     *
     * @return array
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
     * @param array    $from
     * @param int|null $limit
     *
     * @return array
     */
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
