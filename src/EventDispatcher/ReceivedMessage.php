<?php

namespace Prwnr\Streamer\EventDispatcher;

use Prwnr\Streamer\Contracts\StreamableMessage;

/**
 * Class ReceivedMessage.
 */
class ReceivedMessage implements StreamableMessage
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var array
     */
    private $content;

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return array
     */
    public function getContent(): array
    {
        return $this->content;
    }

    /**
     * ReceivedMessage constructor.
     * @param  string  $id
     * @param  array  $content
     */
    public function __construct(string $id, array $content)
    {
        $this->id = $id;
        $content['_id'] = $id;
        $content['data'] = json_decode($content['data'], true);
        $this->content = $content;
    }
}
