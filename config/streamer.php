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
    | Archive Storage Driver
    |--------------------------------------------------------------------------
    |
    | Name of the driver that should be used by StreamArchiver while performing
    | archivisation action.
    | Null driver being the default driver will not store stream message,
    | that will make it only removed.
    |
    | To fully use archiver functionality, the driver should be added to
    | \Prwnr\Streamer\Archiver\StorageManager and save the received message
    | in some kind of database.
    |
    | Driver should implement \Prwnr\Streamer\Contracts\ArchiveStorage contract.
    */
    'archive' => [
        'storage_driver' => 'null'
    ]
];
