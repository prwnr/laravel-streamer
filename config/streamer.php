<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Streamer listen timeout
    |--------------------------------------------------------------------------
    |
    | Miliseconds after which Streamer listen block should timeout
    | Setting 0 never timeouts.
    |
    */
    'listen_timeout' => 0,

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
    | Application events
    |--------------------------------------------------------------------------
    |
    | Events classes that should be invoked with Streamer listen command
    | based on streamer_event_name => [local_events] pairs
    |
    */
    'listen_and_fire' => [
        'example.streamer.event' => [
            //List of local events that should be invoked
            //\App\Events\ExampleEvent::class
        ]
    ],
];
