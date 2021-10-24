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
    public const KEY_SEPARATOR = '-';

    /**
     * Stream message ID.
     */
    private string $id;

    /**
     * Event name.
     */
    private string $name;

    /**
     * Unique event resource identifier.
     */
    private string $identifier;
    private Carbon $date;

    /**
     * Snapshot constructor.
     *
     * @param  string  $id
     * @param  Replayable  $event
     */
    public function __construct(string $id, Replayable $event)
    {
        $this->id = $id;
        $this->name = $event->name();
        $this->identifier = $event->getIdentifier();
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
        return $this->name.self::KEY_SEPARATOR.$this->identifier;
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'identifier' => $this->identifier,
            'date' => $this->date->format('Y-m-d H:i:s')
        ];
    }
}