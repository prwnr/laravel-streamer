<?php

use Prwnr\Streamer\Contracts\Event;
use Prwnr\Streamer\EventDispatcher\ReceivedMessage;
use Prwnr\Streamer\EventDispatcher\Streamer;
use Prwnr\Streamer\History\EventHistory;

/**
 * Usage of Streaming class
 * Listening for events.
 */

// Streaming code that pushes new event on Stream explained in 5_streaming.php file
class ExampleStreamerEvent implements Event
{
    public function name(): string
    {
        return 'example.streamer.event';
    }

    public function type(): string
    {
        return Event::TYPE_EVENT;
    }

    public function payload(): array
    {
        return ['message' => 'content'];
    }
}

$event = new ExampleStreamerEvent();
$streamer = new Streamer(new EventHistory());
$id = $streamer->emit($event);

// Basic listen usage without using group or consumers. It will receive all messages from Stream
// Listen method on a streamer instance allows listening for any new incoming events
// It accepts two arguments, event name (or multiple names) and a callback handler (or multiple handlers associated to events lik [stream => handler])
// Callback is called with argument of ReceivedMessage instance (it has message ID and content)
// and a Streamer instance, that lets you cancel the listener loop whenever you want
// or emit event mid loop
$streamer->listen('example.streamer.event', function (ReceivedMessage $receivedMessage, Streamer $streamer) {
    // do whatever is needed with $receivedMessage
});

// Listen method is a blocking method, therefore it will block any further code execution

// By default listen command is waiting for new messages on a stream without any additional options
// When it gets first message, from now on it waits for any new messages older than last message ID
// It is possible however to start listening from fixed ID, by calling Streamer::startFrom() method
$streamer->startFrom('1541752483932-0');
$streamer->listen('example.streamer.event', function (ReceivedMessage $receivedMessage, Streamer $streamer) {
    // do whatever is needed with $receivedMessage
});

// listen command can also work for a group consumer
// it can be used with asConsumer call before listening
$streamer->asConsumer('consumer_name', 'gorup_name');
// from now on listen will only read messages for given consumer on his group
// and with successfully processing will acknowledge them, so that messages won't get stuck
// in pending list and no other consumer will have to reclaim them
// In this case, listen starts reading messages with '>' special key, that gets messages
// not delivered to any other consumer in current group
$streamer->listen('example.streamer.event', function (ReceivedMessage $receivedMessage) {
    // do whatever is needed with $receivedMessage
});
