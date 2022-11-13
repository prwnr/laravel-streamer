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
    final public const KEY_SEPARATOR = '-';

    public readonly Carbon $date;
    public readonly string $name;

    /**
     * Unique event resource identifier.
     */
    private readonly string $identifier;

    public function __construct(private readonly string $id, Replayable $event)
    {
        $this->name = $event->name();
        $this->identifier = $event->getIdentifier();
        $this->date = Carbon::now();
    }

    /**
     * Returns combination of event name and identifier.
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
