<?php

namespace Tests\Stubs;

use Illuminate\Database\Eloquent\Model;
use Prwnr\Streamer\Concerns\EmitsStreamerEvents;

class EmittingEventsModel extends Model
{
    use EmitsStreamerEvents;

    protected $fillable = [
        'foo',
    ];

}