<?php

use Prwnr\Streamer\Stream;
use Prwnr\Streamer\Streams;

/**
 * Usage of Stream class
 * Explained usage of read() and readRange() methods.
 */

// Creating Stream instance
$stream = new Stream('stream_name');

// Reading message with default options. Returns all messages from a stream
$messages = $stream->read();
/* $messages result format returns array of stream_name => array of messages.
   This is important in case of multiple streams reading which will be explained with Streams class usage
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

// read method comes with two options. 'from' that defines from what ID reading should be started,
// and 'limit', that limits the output to N messages.
$messagesLimit = $stream->read('0', 10);

// Range instance has default values that can be changed in constructor call.
// Those are: start, stop and direction. By default range is set from 'first' to 'last' message in forward direction.
$range = new Stream\Range();

// Reading message from a range. readRange() method requires first argument to be a Stream\Range instance
// Second argument is optional and is used to set limit to how many message should be returned
$rangeMessages = $stream->readRange($range);
/* $rangeMessages result format
Array
(
    [1541684691193-0] => Array
        (
            [message] => content
        )

)
 */

// Setting up backward direction of range messages.
// Pay attention to the fact that 'start' and 'stop' should still be used as low - high value.
// Stream readRange will deal with using correct values
$range = new Stream\Range(Stream\Range::FIRST, Stream\Range::LAST, Stream\Range::BACKWARD);
// Using readRange with limit as well
$rangeMessages = $stream->readRange($range, 10);

/**
 * Usage of Streams class that is designed to manipulate multiple streams
 * Explained usage of read() method
 * reading range is not supported by multiple streams.
 */

// Creating Streams instance with array of streams names that will be manipulated
$streams = new Streams(['first_stream', 'second_stream']);

// read method for streams have two options as well.
// 'from' which in this case is an array of IDs for each stream and 'limit' which is one for all
// By default every stream is read from first existing message
$messages = $streams->read();
/* $messages result format returns array of stream_name => array of messages.
Array
(
    [first_stream] => Array
        (
            [1541670150555-0] => Array
                (
                    [message] => content
                )
        )
    [second_stream] => Array
        (
            [1541612350321-0] => Array
                (
                    [message] => content
                )
        )
)
 */
