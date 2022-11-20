<?php

declare(strict_types=1);

namespace Tests\Stubs;

use Illuminate\Database\Eloquent\Model;
use Prwnr\Streamer\Concerns\EmitsStreamerEvents;

class EmittingEventsModel extends Model
{
    use EmitsStreamerEvents;

    protected $fillable = [
        'foo',
        'id',
    ];

    private bool $shouldStream = true;

    public function __construct(array $attributes = [])
    {
        $this->baseEventName = 'model';
        parent::__construct($attributes);
    }

    public function disableStreaming(): void
    {
        $this->shouldStream = false;
    }

    public function enableStreaming(): void
    {
        $this->shouldStream = true;
    }

    protected function canStream(): bool
    {
        return $this->shouldStream;
    }
}
