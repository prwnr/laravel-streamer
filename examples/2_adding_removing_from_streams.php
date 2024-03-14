<?php

use Prwnr\Streamer\Contracts\Errors\StreamableMessage;
use Prwnr\Streamer\Stream;
use Prwnr\Streamer\Streams;

/**
 * Usage of Stream class that is designed to manipulate single stream
 * Explained usage of add() and delete() methods.
 */

// Creating Stream instance with stream name that will be manipulated
$stream = new Stream('stream_name');

// Message implementation, used to add it to stream
$message = new class () implements StreamableMessage {
    public function getContent(): array
    {
        return [
            'message' => 'content',
        ];
    }
};

// Adding message to Stream with fixed ID. Fixed ID must be higher than the highest ID already stored in Stream.
$fixedId = $stream->add($message, '0');
// Adding message to Stream without fixed ID. By default a special '*' ID will be used. This will create message with automatically incremented ID.
$incrementedId = $stream->add($message);

// Delete message permanently from Stream by ID. Returns 1 as success.
$stream->delete('0');

/**
 * Usage of Streams class that is designed to manipulate multiple streams
 * Explained usage of add() method.
 */

// Creating Streams instance with array of streams names that will be manipulated
$streams = new Streams(['first_stream', 'second_stream']);

// Message implementation, used to add it to stream
$message = new class () implements StreamableMessage {
    public function getContent(): array
    {
        return [
            'message' => 'content',
        ];
    }
};

// Adding message to Streams works similiar to how it works for single stream
// Given message will be added to all streams given in constructor
// Array of all created messages IDs will be returned
// ID argument is optional and specifing one will apply it to every message copy for all streams,
// use it carefully to avoid error due to higher IDs already existing
$messagesIds = $streams->add($message);
