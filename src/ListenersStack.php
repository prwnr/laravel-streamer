<?php

namespace Prwnr\Streamer;

/**
 * Class EventsListenersStack
 */
final class ListenersStack
{

    private static array $events = [];

    /**
     * @param  array  $events
     */
    public static function boot(array $events): void
    {
        self::$events = $events;
    }

    /**
     * Add many listeners to stack at once.
     * Uses ListenersStack::add underneath
     *
     * @param  array  $listenersStack [event => [listeners]]
     */
    public static function addMany(array $listenersStack): void
    {
        foreach ($listenersStack as $event => $listeners) {
            if (is_string($listeners)) {
                $listeners = [$listeners];
            }

            foreach ($listeners as $listener) {
                self::add($event, $listener);
            }
        }
    }

    /**
     * Add event listener to stack
     *
     * @param  string  $event
     * @param  string  $listener
     */
    public static function add(string $event, string $listener): void
    {
        if (!isset(self::$events[$event])) {
            self::$events[$event] = [];
        }

        if (!in_array($listener, self::$events[$event], true)) {
            self::$events[$event][] = $listener;
        }
    }

    /**
     * @param  string  $event
     * @return bool
     */
    public static function hasListener(string $event): bool
    {
        return isset(self::$events[$event]);
    }

    /**
     * @param  string  $event
     * @return array
     */
    public static function getListenersFor(string $event): array
    {
        if (self::hasListener($event)) {
            return self::$events[$event];
        }

        return [];
    }

    /**
     * @return array
     */
    public static function all(): array
    {
        return self::$events;
    }
}