<?php

namespace Tests\Stubs;

use Illuminate\Database\Eloquent\Model;
use Prwnr\Streamer\Concerns\EmitsStreamerEvents;

class EmittingEventsModel extends Model
{
    use EmitsStreamerEvents;

    protected $fillable = [
        'foo', 'id'
    ];

    public function __construct(array $attributes = [])
    {
        $this->baseEventName = 'model';
        parent::__construct($attributes);
    }

}