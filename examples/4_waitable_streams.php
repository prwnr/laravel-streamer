<?php

use Prwnr\Streamer\Stream;

/**
 * Usage of Stream class
 * Explained usage of await method.
 */

// Creating Stream instance
$stream = new Stream('stream_name');

// await method is implementation of Redis XREAD with BLOCK option
// It has two arguments: lastId and timeout (in milliseconds).
// last id determines from which ID stream should be read and a timeout is used for BLOCK command
// By default last ID is set to 0 and timeout as well to 0 (which means to never time out)
$payload = $stream->await('0', 1000);
/* $payload result format is same as for Stream::read() method, it returns array of stream_name => array of messages.
   However, even if it returns payload in such way, there is no option to await on multiple streams.
Array
(
    [stream_name] => Array
        (
            [1541670150555-0] => Array
                (
                    [message] => content
                )
        )
)
 */

// await lastId can be used with special key which is defined in Stream class as NEW_ENTRIES constant,
// a '$', this means that await will return only the next incoming message ignoring all already existing messages
// See Redis XREAD documentation for details
$payloadNewest = $stream->await(Stream::NEW_ENTRIES, 1000);

/**
 * Usage of Consumer class
 * Explained usage of await method on group as a consumer.
 */

// Creating Stream instance
$stream = new Stream('stream_name');
// Consumer accepts a name, stream (from which its name is retrieved) and a group name. All 3 parameters are required
$consumer = new Stream\Consumer('consumer_name', $stream, 'group_name');

// await method is implementation of Redis XREADGROUP with BLOCK option
// Usage is same as for single Stream await
// Awaiting for message on a consumer has also a different NEW_ENTRIEs key, which is '>'. This key get you all messages
// that were not delivered to any other consumer in that group. See Redis XREADGROUP for more details
// It is allowed to use any other ID as last ID as well
$payload = $consumer->await(Stream\Consumer::NEW_ENTRIES, 1000);
/* $payload result format is same as for Stream::read() method, it returns array of stream_name => array of messages.
   However, even if it returns payload in such way, there is no option to await on multiple streams as a consumer.
Array
(
    [stream_name] => Array
        (
            [1541670150555-0] => Array
                (
                    [message] => content
                )
        )
)
 */

/**
 * Acknowledging stream group messages
 * Stream and Consumer, both are implementing a 'Waitable' interface, which defines explained above await method
 * and a 'acknowledge' method.
 * Although both classes are implementing this method, only one has it implemented.
 * Stream class has this method empty, because there is no way to acknowledge messages on a Stream without listening as consumer
 * Consumer class has this method implemented.
 */

// Creating Stream instance
$stream = new Stream('stream_name');
// Creating Consumer instance
$consumer = new Stream\Consumer('consumer_name', $stream, 'group_name');

// acknowledge method simply accepts an ID of a message that should be acknowledged (removed from pending list or available list for other consumer)
// see Redis documentation for more details on XACK method
$consumer->acknowledge('1');
