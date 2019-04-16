<?php

namespace Tests;

use Prwnr\Streamer\ListenersStack;
use Tests\Stubs\AnotherLocalEventStub;
use Tests\Stubs\LocalEventStub;

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
                LocalEventStub::class,
                AnotherLocalEventStub::class
            ]
        ];
        ListenersStack::boot($expected);

        $this->assertEquals($expected, ListenersStack::all());
    }

    public function test_listeners_stack_adds_new_event_listener_to_stack(): void
    {
        $expected = [
            'foo.bar' => [
                LocalEventStub::class,
                AnotherLocalEventStub::class
            ],
            'bar.foo' => [
                LocalEventStub::class
            ]
        ];

        ListenersStack::add('foo.bar', LocalEventStub::class);
        ListenersStack::add('foo.bar', AnotherLocalEventStub::class);
        ListenersStack::add('bar.foo', LocalEventStub::class);

        $this->assertEquals($expected, ListenersStack::all());
    }

    public function test_listeners_stack_wont_add_listener_to_event_when_its_already_in_stack(): void
    {
        $expected = [
            'foo.bar' => [
                LocalEventStub::class,
                AnotherLocalEventStub::class
            ],
        ];

        ListenersStack::add('foo.bar', LocalEventStub::class);
        ListenersStack::add('foo.bar', AnotherLocalEventStub::class);
        ListenersStack::add('foo.bar', AnotherLocalEventStub::class);

        $this->assertEquals($expected, ListenersStack::all());
    }
}