<?php

namespace Prwnr\Streamer\History;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Carbon;
use Prwnr\Streamer\Contracts\Replayable;

/**
 * Class Snapshot
 */
class Snapshot implements Arrayable
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var Replayable
     */
    private $event;

    /**
     * @var Carbon
     */
    private $date;

    /**
     * Snapshot constructor.
     *
     * @param  string  $id
     * @param  Replayable  $event
     */
    public function __construct(string $id, Replayable $event)
    {
        $this->id = $id;
        $this->event = $event;
        $this->date = Carbon::now();
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return Replayable
     */
    public function getEvent(): Replayable
    {
        return $this->event;
    }

    /**
     * @return Carbon
     */
    public function getDate(): Carbon
    {
        return $this->date;
    }

    /**
     * Returns combination of event name and identifier.
     *
     * @return string
     */
    public function getKey(): string
    {
        return $this->event->name().'-'.$this->event->getIdentifier();
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->event->name(),
            'identifier' => $this->event->getIdentifier(),
            'date' => $this->date->format('Y-m-d H:i:s')
        ];
    }
}