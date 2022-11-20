<?php

declare(strict_types=1);

namespace Prwnr\Streamer\EventDispatcher;

use Illuminate\Support\Arr;
use Prwnr\Streamer\Contracts\StreamableMessage;

abstract class StreamMessage implements StreamableMessage
{
    public function __construct(protected readonly array $content = [])
    {
    }

    public function getId(): string
    {
        return $this->content['_id'] ?? '';
    }

    public function getEventName(): string
    {
        return $this->content['name'] ?? '';
    }

    public function getData(): array
    {
        return $this->content['data'] ?? [];
    }

    public function getContent(): array
    {
        return $this->content;
    }

    /**
     * Retrieves values directly from the content data.
     *
     * @param  null|string  $key  dot.notation string
     */
    public function get(?string $key = null, $default = null): mixed
    {
        return Arr::get($this->getData(), $key, $default);
    }

    /**
     * Retrieves values directly from the content data.
     */
    public function only(array|string $keys): array
    {
        return Arr::only($this->getData(), $keys);
    }

    /**
     * Retrieves values directly from the content data.
     */
    public function except(array|string $keys): array
    {
        return Arr::except($this->getData(), $keys);
    }
}
