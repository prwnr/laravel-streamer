<?php

use Prwnr\Streamer\Contracts\Event;
use Prwnr\Streamer\EventDispatcher\Streamer;
use Prwnr\Streamer\History\EventHistory;

/**
 * Usage of Streaming class
 * Emitting new events.
 */

// Basic implementation of Streamer event
class ExampleStreamerEvent implements Event
{
    /**
     * Require name method, must return a string.
     * Event name can be anything, but remember that it will be used for listening.
     *
     * @return string
     */
    public function name(): string
    {
        return 'example.streamer.event';
    }

    /**
     * Required type method, must return a string.
     * Type can be any string or one of predefined types from Event.
     */
    public function type(): string
    {
        return Event::TYPE_EVENT;
    }

    /**
     * Required payload method, must return array
     * This array will be your message data content.
     */
    public function payload(): array
    {
        return ['message' => 'content'];
    }
}

// event instance
$event = new ExampleStreamerEvent();

// Streamer instance
$streamer = new Streamer(new EventHistory());

// emit method requires one argument that must be implementation of Event
// in response in returns ID of newly created message
$id = $streamer->emit($event);

// Streamer facade can be used instead of instance
// usage is exactly the same as for instance
$facadeId = \Prwnr\Streamer\Facades\Streamer::emit($event);
