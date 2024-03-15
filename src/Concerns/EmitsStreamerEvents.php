<?php

declare(strict_types=1);

namespace Prwnr\Streamer\Concerns;

use Illuminate\Database\Eloquent\Model;
use Prwnr\Streamer\Eloquent\EloquentModelEvent;
use Prwnr\Streamer\Facades\Streamer;

trait EmitsStreamerEvents
{
    protected string $baseEventName;

    /**
     * Boot event listeners.
     */
    public static function bootEmitsStreamerEvents(): void
    {
        static::saved(static function (Model $model): void {
            $model->postSave();
        });

        static::created(static function (Model $model): void {
            $model->postCreate();
        });

        static::deleted(static function (Model $model): void {
            $model->postDelete();
        });
    }

    /**
     * Called after record is successfully updated.
     */
    public function postSave(): void
    {
        if (!$this->wasChanged() || !$this->canStream()) {
            return;
        }

        $payload = $this->makeBasePayload();
        foreach ($this->getChanges() as $field => $change) {
            $payload['fields'][] = $field;
            $payload['before'][$field] = $this->getOriginal($field);
            $payload['after'][$field] = $change;
        }

        Streamer::emit(new EloquentModelEvent($this->getEventName('updated'), $payload));
    }

    /**
     * Called after record is successfully created.
     */
    public function postCreate(): void
    {
        if (!$this->canStream()) {
            return;
        }
        $payload = $this->makeBasePayload();
        foreach ($this->getAttributes() as $field => $change) {
            $payload['fields'][] = $field;
            $payload['before'][$field] = null;
            $payload['after'][$field] = $change;
        }

        Streamer::emit(new EloquentModelEvent($this->getEventName('created'), $payload));
    }

    /**
     * Called after record is successfully deleted.
     */
    public function postDelete(): void
    {
        if (!$this->canStream()) {
            return;
        }

        $payload = $this->makeBasePayload();
        $payload['deleted'] = true;
        Streamer::emit(new EloquentModelEvent($this->getEventName('deleted'), $payload));
    }

    /**
     * Method that can be overridden to add custom logic which will determine
     * whether the given model should have events emitted or not.
     * Returns true by default, emitting events for any case.
     */
    protected function canStream(): bool
    {
        return true;
    }

    /**
     * Method that can be overridden to add additional data to each event payload.
     * It will be added as 'top' level array. If method returns empty array,
     * then the 'additional' data won't be added to payload.
     */
    protected function getAdditionalPayloadData(): array
    {
        return [];
    }

    private function makeBasePayload(): array
    {
        return array_filter([
            $this->getKeyName() => $this->getKey(),
            'additional' => $this->getAdditionalPayloadData(),
        ]);
    }

    private function getEventName(string $action): string
    {
        $suffix = '.' . $action;
        $name = class_basename($this) . $suffix;
        if ($this->baseEventName) {
            $name = $this->baseEventName . $suffix;
        }

        return strtolower($name);
    }
}
