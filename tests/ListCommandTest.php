<?php

declare(strict_types=1);

namespace Tests;

use Prwnr\Streamer\ListenersStack;
use Tests\Stubs\AnotherLocalListener;
use Tests\Stubs\LocalListener;

class ListCommandTest extends TestCase
{
    public function test_lists_all_events_with_their_listeners(): void
    {
        ListenersStack::add('foo.bar', LocalListener::class);
        ListenersStack::add('other.foo.bar', LocalListener::class);
        ListenersStack::add('other.foo.bar', AnotherLocalListener::class);

        $this->artisan('streamer:list')
            ->expectsOutput('+------------------------+----------------------------------+')
            ->expectsOutput('| Event                  | Listeners                        |')
            ->expectsOutput('+------------------------+----------------------------------+')
            ->expectsOutput('| example.streamer.event | none                             |')
            ->expectsOutput('| foo.bar                | Tests\Stubs\LocalListener        |')
            ->expectsOutput('| other.foo.bar          | Tests\Stubs\LocalListener        |')
            ->expectsOutput('|                        | Tests\Stubs\AnotherLocalListener |')
            ->expectsOutput('+------------------------+----------------------------------+')
            ->assertExitCode(0);
    }

    public function test_lists_all_events(): void
    {
        ListenersStack::add('foo.bar', LocalListener::class);
        ListenersStack::add('other.foo.bar', LocalListener::class);
        ListenersStack::add('other.foo.bar', AnotherLocalListener::class);

        $this->artisan('streamer:list', ['--compact' => true])
            ->expectsOutput('Event                  ')
            ->expectsOutput('example.streamer.event ')
            ->expectsOutput('foo.bar                ')
            ->expectsOutput('other.foo.bar          ')
            ->assertExitCode(0);
    }
}
