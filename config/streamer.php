<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Listener timeout
    |--------------------------------------------------------------------------
    |
    | Seconds after which Streamer listen block should timeout
    | Setting 0 never timeouts.
    |
    | Time in seconds
    |
    */
    'listen_timeout' => 0,

    /*
    |--------------------------------------------------------------------------
    | Streamer read timeout
    |--------------------------------------------------------------------------
    |
    | Seconds after which Streamer listen block should timeout
    | Setting 0 never timeouts.
    |
    | Time in seconds
    |
    */
    'stream_read_timeout' => 0,

    /*
    |--------------------------------------------------------------------------
    | Streamer reading sleep
    |--------------------------------------------------------------------------
    |
    | Seconds of a sleep time that happens between reading messages from Stream
    |
    | Time in seconds
    |
    */
    'read_sleep' => 1,

    /*
    |--------------------------------------------------------------------------
    | Streamer Redis connection
    |--------------------------------------------------------------------------
    |
    | Connection name which Streamer should use for all Redis commands
    |
    */
    'redis_connection' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Streamer event domain
    |--------------------------------------------------------------------------
    |
    | Domain name which streamer should use when
    | building message with JSON schema
    |
    */
    'domain' => env('APP_NAME', ''),

    /*
    |--------------------------------------------------------------------------
    | Application handlers
    |--------------------------------------------------------------------------
    |
    | Handlers classes that should be invoked with Streamer listen command
    | based on streamer.event.name => [local_handlers] pairs
    |
    | Local handlers should implement MessageReceiver contract
    |
    */
    'listen_and_fire' => [
        'example.streamer.event' => [
            //List of local listeners that should be invoked
            //\App\Listeners\ExampleListener::class
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Archive Model
    |--------------------------------------------------------------------------
    |
    | Model class that should be used to archive the stream message
    | and store it in database.
    |
    */
    'archive_model' => null,
];
