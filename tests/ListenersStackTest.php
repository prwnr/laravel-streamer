<?php

declare(strict_types=1);

namespace Tests;

use Prwnr\Streamer\ListenersStack;
use Tests\Stubs\AnotherLocalListener;
use Tests\Stubs\LocalListener;

class ListenersStackTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ListenersStack::boot([]);
    }

    public function test_listeners_stack_is_booted_with_array_of_listeners(): void
    {
        $expected = [
            'foo.bar' => [
                LocalListener::class,
                AnotherLocalListener::class,
            ],
        ];
        ListenersStack::boot($expected);

        $this->assertEquals($expected, ListenersStack::all());
    }

    public function test_listeners_stack_adds_new_event_listener_to_stack(): void
    {
        $expected = [
            'foo.bar' => [
                LocalListener::class,
                AnotherLocalListener::class,
            ],
            'bar.foo' => [
                LocalListener::class,
            ],
        ];

        ListenersStack::add('foo.bar', LocalListener::class);
        ListenersStack::add('foo.bar', AnotherLocalListener::class);
        ListenersStack::add('bar.foo', LocalListener::class);

        $this->assertEquals($expected, ListenersStack::all());
    }

    public function test_listeners_stack_adds_new_event_listeners_to_stack_as_array(): void
    {
        $expected = [
            'foo.bar' => [
                LocalListener::class,
                AnotherLocalListener::class,
            ],
            'bar.foo' => LocalListener::class,
        ];

        ListenersStack::addMany($expected);

        $expected = [
            'foo.bar' => [
                LocalListener::class,
                AnotherLocalListener::class,
            ],
            'bar.foo' => [
                LocalListener::class,
            ],
        ];
        $this->assertEquals($expected, ListenersStack::all());
    }

    public function test_listeners_stack_wont_add_listener_to_event_when_its_already_in_stack(): void
    {
        $expected = [
            'foo.bar' => [
                LocalListener::class,
                AnotherLocalListener::class,
            ],
        ];

        ListenersStack::add('foo.bar', LocalListener::class);
        ListenersStack::add('foo.bar', AnotherLocalListener::class);
        ListenersStack::add('foo.bar', AnotherLocalListener::class);

        $this->assertEquals($expected, ListenersStack::all());
    }

    public function test_listeners_stack_has_listener_for_event(): void
    {
        $expected = [
            'foo.bar' => [
                LocalListener::class,
                AnotherLocalListener::class,
            ],
            'bar.foo' => LocalListener::class,
        ];

        ListenersStack::addMany($expected);

        $this->assertTrue(ListenersStack::hasListener('foo.bar'));
        $this->assertTrue(ListenersStack::hasListener('bar.foo'));
    }

    public function test_listeners_stack_get_listeners_for_event(): void
    {
        $expected = [
            'foo.bar' => [
                LocalListener::class,
                AnotherLocalListener::class,
            ],
            'bar.foo' => LocalListener::class,
        ];

        ListenersStack::addMany($expected);

        $this->assertEquals([
            LocalListener::class,
            AnotherLocalListener::class,
        ], ListenersStack::getListenersFor('foo.bar'));
        $this->assertEquals([LocalListener::class], ListenersStack::getListenersFor('bar.foo'));
    }
}
