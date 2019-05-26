<?php

namespace Prwnr\Streamer\Facades;

use Illuminate\Support\Facades\Facade;
use Prwnr\Streamer\Contracts\Event;

/**
 * Class Streamer.
 *
 * @method static \Prwnr\Streamer\EventDispatcher\Streamer startFrom(string $startFrom)
 * @method static \Prwnr\Streamer\EventDispatcher\Streamer asConsumer(string $consumer, string $group)
 * @method static string emit(Event $event, string $id = '*')
 * @method static void listen(string $event, callable $handler)
 *
 * @see \Prwnr\Streamer\EventDispatcher\Streamer
 */
class Streamer extends Facade
{
    /**
     * {@inheritdoc}
     */
    protected static function getFacadeAccessor(): string
    {
        return 'Streamer';
    }
}
