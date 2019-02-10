<?php

namespace Prwnr\Streamer\EventDispatcher;

/**
 * Trait HashableMessage.
 */
trait HashableMessage
{
    /**
     * Creates a key from payload: type, name, domain and data; and makes hash out of it.
     */
    protected function hashIt(): void
    {
        if (!$this->content) {
            return;
        }

        $data = \is_array($this->content['data']) || \is_object($this->content['data']) ? json_encode($this->content['data']) : $this->content['data'];
        $key = $this->content['type'].$this->content['name'].$this->content['domain'].$data;
        $hash = hash('SHA256', $key);
        $this->content['hash'] = $hash;
    }
}
