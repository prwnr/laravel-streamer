<?php

namespace Prwnr\Streamer\Concerns;

use JsonException;

/**
 * Trait HashableMessage.
 */
trait HashableMessage
{
    /**
     * Creates a key from payload: type, name, domain and data; and makes hash out of it.
     * @throws JsonException
     */
    protected function hashIt(): void
    {
        if (!$this->content) {
            return;
        }

        $data = $this->content['data'];
        if (is_array($this->content['data']) || is_object($this->content['data'])) {
            $data = json_encode($this->content['data'], JSON_THROW_ON_ERROR);
        }

        $key = $this->content['type'].$this->content['name'].$this->content['domain'].$data;
        $hash = hash('SHA256', $key);
        $this->content['hash'] = $hash;
    }
}
