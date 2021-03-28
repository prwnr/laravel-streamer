<?php

namespace Prwnr\Streamer\Concerns;

use Illuminate\Database\Eloquent\Model;
use Prwnr\Streamer\Eloquent\EloquentModelEvent;
use Prwnr\Streamer\Facades\Streamer;

/**
 * Trait EmitsStreamerEvents.
 */
trait EmitsStreamerEvents
{
    protected string $baseEventName;

    /**
     * Boot event listeners.
     */
    public static function bootEmitsStreamerEvents(): void
    {
        static::saved(static function (Model $model) {
            $model->postSave();
        });

        static::created(static function (Model $model) {
            $model->postCreate();
        });

        static::deleted(static function (Model $model) {
            $model->postDelete();
        });
    }

    /**
     * Called after record is successfully updated.
     */
    public function postSave(): void
    {
        if (! $this->wasChanged()) {
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
        $payload = $this->makeBasePayload();
        $payload['deleted'] = true;
        Streamer::emit(new EloquentModelEvent($this->getEventName('deleted'), $payload));
    }

    /**
     * Method that can be overridden to add additional data to each event payload.
     * It will be added as 'top' level array. If method returns empty array,
     * then the 'additional' data won't be added to payload.
     *
     * @return array
     */
    protected function getAdditionalPayloadData(): array
    {
        return [];
    }

    /**
     * @param string $action
     * @return string
     */
    private function getEventName(string $action): string
    {
        $suffix = '.'.$action;
        $name = class_basename($this).$suffix;
        if ($this->baseEventName) {
            $name = $this->baseEventName.$suffix;
        }

        return strtolower($name);
    }

    /**
     * @return array
     */
    private function makeBasePayload(): array
    {
        return array_filter([
            $this->getKeyName() => $this->getKey(),
            'additional' => $this->getAdditionalPayloadData()
        ]);
    }

}