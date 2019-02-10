<?php

namespace Tests\Stubs;

use Prwnr\Streamer\Eloquent\EmitsStreamerEvents;

class EmittingEventsModel extends \Illuminate\Database\Eloquent\Model
{
    use EmitsStreamerEvents;

    protected $fillable = [
        'foo',
    ];

}