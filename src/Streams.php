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
     * @param StreamableMessage $message
     * @param string            $id
     *
     * @return mixed
     */
    public function add(StreamableMessage $message, string $id = '*')
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
     * @return mixed
     */
    public function read(array $from = [], ?int $limit = null)
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
