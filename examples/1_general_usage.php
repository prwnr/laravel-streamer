<?php

use Prwnr\Streamer\Stream;

/**
 * Usage of Stream and Consumer classes
 * Explained usage of methods like info, groups, consumers, pending, claim, len.
 */

// Creating Stream instance
$stream = new Stream('stream_name');

// info method returns general information about a Stream such as:
// length, radix-tree-keys, radix-tree-nodes, groups, last-generated-id, first-entry, last-entry
// see Redis XINFO for details
$info = $stream->info();

// groups method returns information about all Stream groups, such as:
// name, number of consumers, number of pending messages
// see Redis XINFO for details
$groups = $stream->groups();

// groupExists method uses above groups method to fetch all groups from a Stream
// and check if given group exists
$exists = $stream->groupExists('group');

// consumers method returns information about all consumers from specific group, such as
// name, number of pending messages, idle time
// see Redis XINFO for details
$consumers = $stream->consumers('group');

// createGroup method creates a new group on a stream with given name
// this method has two optional arguments:
// 'from': that determines from what message group should start getting.
// This option is by default set to fetch all messages
// 'createStreamIfNotExist': determines if empty stream should be created with group creation
// this is an implementation of MKSTREAM option for XGROUP command, to avoid issues that occurs
// when a group creation is attempted on non existing stream. This options is by default set to true
$stream->createGroup('group');

// len returns number of messages of current stream
$length = $stream->len();

// pending method has two arguments. a group name and a consumer name, where consumer is optional
// This method returns a list of all pending messages from given group with information about:
// message ID, current consumer owner, idle time and the number of times this message was delivered
$groupPending = $stream->pending('group');
// when used with consumer argument it simply lists only message pending for that single consumer
$groupConsumerPending = $stream->pending('group', 'consumer');

// Creating Stream and Consumer instances
$stream = new Stream('stream_name');
$consumer = new Stream\Consumer('consumer', $stream, 'group');

// pending message on a consumer is a call to Stream::pending method with group and consumer names already provided
$consumerPending = $consumer->pending();

// claim is a method that can only be called on a Consumer instance
// this method implements XCLAIM command and claims messages on a given stream
// from other consumers to current consumer. Messages stays are pending till acknowledged
// this method accepts array of messages IDs to claim and idle time in milliseconds
// idletime determines how old messages should be to reclaim them
$consumer->claim(['0', '1'], 1);
