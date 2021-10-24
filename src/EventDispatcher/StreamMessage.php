<?php

namespace Prwnr\Streamer\EventDispatcher;

use Illuminate\Support\Arr;
use Prwnr\Streamer\Contracts\StreamableMessage;

abstract class StreamMessage implements StreamableMessage
{
    /**
     * @var array
     */
    protected array $content;

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->content['_id'] ?? '';
    }

    /**
     * @return string
     */
    public function getEventName(): string
    {
        return $this->content['name'] ?? '';
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->content['data'] ?? [];
    }

    /**
     * @return array
     */
    public function getContent(): array
    {
        return $this->content;
    }

    /**
     * Retrieves values directly from the content data.
     *
     * @param  null|string  $key  dot.notation string
     * @param  null  $default
     * @return mixed
     */
    public function get(?string $key = null, $default = null)
    {
        return Arr::get($this->getData(), $key, $default);
    }

    /**
     * Retrieves values directly from the content data.
     *
     * @param  array|string  $keys
     * @return array
     */
    public function only($keys): array
    {
        return Arr::only($this->getData(), $keys);
    }

    /**
     * Retrieves values directly from the content data.
     *
     * @param  array|string  $keys
     * @return array
     */
    public function except($keys): array
    {
        return Arr::except($this->getData(), $keys);
    }
}