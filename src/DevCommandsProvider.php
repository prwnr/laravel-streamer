<?php

declare(strict_types=1);

namespace Prwnr\Streamer;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\ServiceProvider;

class DevCommandsProvider extends ServiceProvider
{
    /**
     * Register configured listeners as "php artisan dev" processes.
     */
    public function boot(): void
    {
        if (! class_exists(DevCommands::class) || ! config('streamer.dev.enabled')) {
            return;
        }

        $this->app['events']->listen(CommandStarting::class, function (CommandStarting $event): void {
            if ($event->command !== 'dev') {
                return;
            }

            foreach (config('streamer.dev.listeners', []) as $name => $listener) {
                $events = $this->eventsToListen($listener);

                if ($events === []) {
                    continue;
                }

                DevCommands::artisan($this->buildListenCommand($listener, $events), $name);
            }
        });
    }

    /**
     * Events of a dev listener entry that have local listeners, or null when it listens to all.
     */
    private function eventsToListen(array $listener): ?array
    {
        $events = $listener['events'] ?? '*';

        if ($events === '*') {
            return ListenersStack::all() === [] ? [] : null;
        }

        return array_values(array_filter(
            array_map('trim', explode(',', (string) $events)),
            static fn (string $event): bool => $event !== '' && ListenersStack::hasListener($event),
        ));
    }

    /**
     * Build the streamer:listen command for a dev listener entry.
     */
    private function buildListenCommand(array $listener, ?array $events): string
    {
        return trim(sprintf(
            'streamer:listen %s --group=%s --consumer=%s %s',
            $events === null ? '--all' : $this->escape(implode(',', $events)),
            $this->escape((string) ($listener['group'] ?? 'dev')),
            $this->escape((string) ($listener['consumer'] ?? 'local')),
            $listener['args'] ?? '',
        ));
    }

    /**
     * Quote a value for the shell only when it isn't a plain name.
     */
    private function escape(string $value): string
    {
        return preg_match('/^[\w.,:-]+$/', $value) ? $value : escapeshellarg($value);
    }
}
