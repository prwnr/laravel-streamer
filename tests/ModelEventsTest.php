<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\Concerns\InteractsWithRedis;
use Prwnr\Streamer\Contracts\Event;
use Prwnr\Streamer\Eloquent\EloquentModelEvent;
use Prwnr\Streamer\Stream;
use Tests\Stubs\EmittingEventsModel;
use Tests\Stubs\EmittingEventsWithAdditionalModel;

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
        $this->redis['phpredis']->connection()->flushall();
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
        $model = new EmittingEventsModel(['id' => 123, 'foo' => 'bar']);
        $model->postCreate();

        $expected = [
            'id' => 123,
            'fields' => [
                'id',
                'foo',
            ],
            'before' => [
                'id' => null,
                'foo' => null,
            ],
            'after' => [
                'id' => 123,
                'foo' => 'bar',
            ],

        ];
        $stream = new Stream('model.created');
        $actual = $stream->read();
        $this->assertNotEmpty($actual);
        $this->assertArrayHasKey('model.created', $actual);
        $message = array_pop($actual['model.created']);
        $this->assertEquals(json_encode($expected, JSON_THROW_ON_ERROR), $message['data']);
    }

    public function test_saving_model_emits_updated_event_to_stream(): void
    {
        $model = new EmittingEventsModel(['id' => 123, 'foo' => 'bar']);
        $model->syncOriginal();
        $model->foo = 'foobar';
        $model->syncChanges();
        $model->postSave();

        $expected = [
            'id' => 123,
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
        $stream = new Stream('model.updated');
        $actual = $stream->read();
        $this->assertNotEmpty($actual);
        $this->assertArrayHasKey('model.updated', $actual);
        $message = array_pop($actual['model.updated']);
        $this->assertEquals(json_encode($expected, JSON_THROW_ON_ERROR), $message['data']);
    }

    public function test_saving_model_without_changes_wont_emit_updated_event_to_stream(): void
    {
        $model = new EmittingEventsModel(['id' => 123, 'foo' => 'bar']);
        $model->syncOriginal();
        $model->foo = 'bar';
        $model->syncChanges();
        $model->postSave();

        $stream = new Stream('model.updated');
        $actual = $stream->read();
        $this->assertEmpty($actual);
    }

    public function test_deleting_model_emits_deleted_event_to_stream(): void
    {
        $model = new EmittingEventsModel(['id' => 123, 'foo' => 'bar']);
        $model->postDelete();

        $expected = [
            'id' => 123,
            'deleted' => true,
        ];
        $stream = new Stream('model.deleted');
        $actual = $stream->read();
        $this->assertNotEmpty($actual);
        $this->assertArrayHasKey('model.deleted', $actual);
        $message = array_pop($actual['model.deleted']);
        $this->assertEquals(json_encode($expected, JSON_THROW_ON_ERROR), $message['data']);
    }

    public function test_model_with_changed_base_name_emits_different_name_of_event_to_stream(): void
    {
        $model = new class () extends EmittingEventsModel {
            public function __construct(array $attributes = [])
            {
                parent::__construct($attributes);
                $this->baseEventName = 'child.model';
            }
        };
        $model->postCreate();

        $stream = new Stream('child.model.created');
        $actual = $stream->read();
        $this->assertNotEmpty($actual);
        $this->assertArrayHasKey('child.model.created', $actual);
    }

    public function test_creating_model_with_additional_payload_data_emits_created_event_to_stream_with_that_data(
    ): void {
        $model = new EmittingEventsWithAdditionalModel(['id' => 123, 'foo' => 'bar']);
        $model->setAdditionalPayloadData(['foo' => 'bar']);
        $model->postCreate();

        $expected = [
            'id' => 123,
            'additional' => ['foo' => 'bar'],
            'fields' => [
                'id',
                'foo',
            ],
            'before' => [
                'id' => null,
                'foo' => null,
            ],
            'after' => [
                'id' => 123,
                'foo' => 'bar',
            ],
        ];

        $stream = new Stream('model.with.additional.created');
        $actual = $stream->read();
        $this->assertNotEmpty($actual);
        $this->assertArrayHasKey('model.with.additional.created', $actual);
        $message = array_pop($actual['model.with.additional.created']);
        $this->assertEquals(json_encode($expected, JSON_THROW_ON_ERROR), $message['data']);
    }

    public function test_creating_model_with_empty_additional_payload_data_emits_created_event_to_stream_without_that_data(
    ): void {
        $model = new EmittingEventsWithAdditionalModel(['id' => 123, 'foo' => 'bar']);
        $model->setAdditionalPayloadData([]);
        $model->postCreate();

        $expected = [
            'id' => 123,
            'fields' => [
                'id',
                'foo',
            ],
            'before' => [
                'id' => null,
                'foo' => null,
            ],
            'after' => [
                'id' => 123,
                'foo' => 'bar',
            ],
        ];

        $stream = new Stream('model.with.additional.created');
        $actual = $stream->read();
        $this->assertNotEmpty($actual);
        $this->assertArrayHasKey('model.with.additional.created', $actual);
        $message = array_pop($actual['model.with.additional.created']);
        $this->assertEquals(json_encode($expected, JSON_THROW_ON_ERROR), $message['data']);
    }

    public function test_model_with_disabled_streaming_wont_emit_any_events(): void
    {
        $model = new EmittingEventsModel(['id' => 123, 'foo' => 'bar']);
        $model->disableStreaming();

        $model->postCreate();

        $model->syncOriginal();
        $model->foo = 'bar';
        $model->syncChanges();
        $model->postSave();

        $model->postDelete();

        $stream = new Stream('model.created');
        $this->assertEmpty($stream->read());

        $stream = new Stream('model.updated');
        $this->assertEmpty($stream->read());

        $stream = new Stream('model.deleted');
        $this->assertEmpty($stream->read());

        $model->enableStreaming();

        $model->postCreate();

        $model->syncOriginal();
        $model->foo = 'foobar';
        $model->syncChanges();
        $model->postSave();

        $model->postDelete();

        $stream = new Stream('model.created');
        $this->assertNotEmpty($stream->read());

        $stream = new Stream('model.updated');
        $this->assertNotEmpty($stream->read());

        $stream = new Stream('model.deleted');
        $this->assertNotEmpty($stream->read());
    }
}
