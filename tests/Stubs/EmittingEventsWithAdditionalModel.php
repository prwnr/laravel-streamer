<?php

declare(strict_types=1);

namespace Tests\Stubs;

use Illuminate\Database\Eloquent\Model;
use Prwnr\Streamer\Concerns\EmitsStreamerEvents;

class EmittingEventsWithAdditionalModel extends Model
{
    use EmitsStreamerEvents;

    protected array $additional;

    protected $fillable = [
        'foo',
        'id',
    ];

    public function __construct(array $attributes = [])
    {
        $this->baseEventName = 'model.with.additional';
        parent::__construct($attributes);
    }

    public function setAdditionalPayloadData(array $data): void
    {
        $this->additional = $data;
    }

    protected function getAdditionalPayloadData(): array
    {
        return $this->additional;
    }
}
