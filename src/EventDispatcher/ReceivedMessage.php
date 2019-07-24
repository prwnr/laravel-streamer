<?php

namespace Prwnr\Streamer\EventDispatcher;

use Illuminate\Support\Arr;
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
     * @param  null|string  $key  dot.notation string
     * @param  null  $default
     * @return mixed
     */
    public function get(?string $key = null, $default = null)
    {
        return Arr::get($this->content['data'] ?? [], $key, $default);
    }

    /**
     * @param  array|string  $keys
     * @return array
     */
    public function only($keys): array
    {
        return Arr::only($this->content['data'] ?? [], $keys);
    }

    /**
     * @param  array|string  $keys
     * @return array
     */
    public function except($keys): array
    {
        return Arr::except($this->content['data'] ?? [], $keys);
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
