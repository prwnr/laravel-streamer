<?php

namespace Tests;

use Prwnr\Streamer\Stream;
use Prwnr\Streamer\Contracts\Event;
use Tests\Stubs\EmittingEventsModel;
use Prwnr\Streamer\Eloquent\EloquentModelEvent;
use Illuminate\Foundation\Testing\Concerns\InteractsWithRedis;

class ModelEventsTest extends TestCase
{
    use InteractsWithRedis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRedis();
        $this->app['config']->set('streamer.listen_timeout', 0.01);
        $this->app['config']->set('streamer.stream_read_timeout', 0.01);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->redis['predis']->connection()->flushall();
        $this->tearDownRedis();
    }

    public function test_model_event_implements_event_contract(): void
    {
        $modelEvent = new EloquentModelEvent('foo.bar', ['foo' => 'bar']);

        $this->assertInstanceOf(Event::class, $modelEvent);
        $this->assertSame('foo.bar', $modelEvent->name());
        $this->assertSame(['foo' => 'bar'], $modelEvent->payload());
        $this->assertSame(Event::TYPE_EVENT, $modelEvent->type());
    }

    public function test_creating_model_emits_created_event_to_stream(): void
    {
        $model = new EmittingEventsModel(['foo' => 'bar']);
        $model->postCreate();

        $expected = [
            'id' => null,
            'fields' => [
                'foo',
            ],
            'before' => [
                'foo' => null,
            ],
            'after' => [
                'foo' => 'bar',
            ],

        ];
        $stream = new Stream('emittingeventsmodel.created');
        $actual = $stream->read();
        $this->assertNotEmpty($actual);
        $this->assertArrayHasKey('emittingeventsmodel.created', $actual);
        $message = array_pop($actual['emittingeventsmodel.created']);
        $this->assertEquals(json_encode($expected), $message['data']);
    }

    public function test_saving_model_emits_updated_event_to_stream(): void
    {
        $model = new EmittingEventsModel(['foo' => 'bar']);
        $model->syncOriginal();
        $model->foo = 'foobar';
        $model->syncChanges();
        $model->postSave();

        $expected = [
            'id' => null,
            'fields' => [
                'foo',
            ],
            'before' => [
                'foo' => 'bar',
            ],
            'after' => [
                'foo' => 'foobar',
            ],
        ];
        $stream = new Stream('emittingeventsmodel.updated');
        $actual = $stream->read();
        $this->assertNotEmpty($actual);
        $this->assertArrayHasKey('emittingeventsmodel.updated', $actual);
        $message = array_pop($actual['emittingeventsmodel.updated']);
        $this->assertEquals(json_encode($expected), $message['data']);
    }

    public function test_saving_model_withoun_changes_wont_emit_updated_event_to_stream(): void
    {
        $model = new EmittingEventsModel(['foo' => 'bar']);
        $model->syncOriginal();
        $model->foo = 'bar';
        $model->syncChanges();
        $model->postSave();

        $stream = new Stream('emittingeventsmodel.updated');
        $actual = $stream->read();
        $this->assertEmpty($actual);
    }

    public function test_deleting_model_emits_deleted_event_to_stream(): void
    {
        $model = new EmittingEventsModel(['foo' => 'bar']);
        $model->postDelete();

        $expected = [
            'id' => null,
            'deleted' => true,
        ];
        $stream = new Stream('emittingeventsmodel.deleted');
        $actual = $stream->read();
        $this->assertNotEmpty($actual);
        $this->assertArrayHasKey('emittingeventsmodel.deleted', $actual);
        $message = array_pop($actual['emittingeventsmodel.deleted']);
        $this->assertEquals(json_encode($expected), $message['data']);
    }

    public function test_model_with_changed_base_name_emits_different_name_of_event_to_stream(): void
    {
        $model = new class extends EmittingEventsModel {
            public function __construct(array $attributes = [])
            {
                $this->baseEventName = 'child.model';
                parent::__construct($attributes);
            }
        };
        $model->postCreate();

        $stream = new Stream('child.model.created');
        $actual = $stream->read();
        $this->assertNotEmpty($actual);
        $this->assertArrayHasKey('child.model.created', $actual);
    }

}