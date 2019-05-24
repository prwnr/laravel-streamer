<?php

namespace Tests;

use Prwnr\Streamer\Contracts\Event;
use Prwnr\Streamer\Contracts\StreamableMessage;
use Prwnr\Streamer\Concerns\HashableMessage;
use Prwnr\Streamer\EventDispatcher\Message;
use Prwnr\Streamer\EventDispatcher\ReceivedMessage;

class MessageTest extends TestCase
{
    public function test_message_is_created_with_content(): void
    {
        $meta = [
            'type'    => Event::TYPE_EVENT,
            'domain'  => config('streamer.domain'),
            'name'    => 'foo.bar',
            'created' => time(),
        ];
        $data = ['foo' => 'bar'];
        $message = new Message($meta, $data);

        $actual = $message->getContent();

        $this->assertInstanceOf(StreamableMessage::class, $message);
        $this->assertNotEmpty($actual);
        $this->assertEquals('1.3', $actual['version']);
        $this->assertArraySubset($meta, $actual);
        $this->assertArrayHasKey('hash', $actual);
        $this->assertJson($actual['data']);
        $this->assertEquals(json_encode($data), $actual['data']);
    }

    public function test_received_message_is_created_with_id_and_content(): void
    {
        $data = ['foo' => 'bar'];
        $expectedId = '1-0';
        $expected = [
            '_id'  => $expectedId,
            'data' => $data,
        ];

        $message = new ReceivedMessage($expectedId, [
            '_id'  => $expectedId,
            'data' => json_encode($data),
        ]);

        $this->assertInstanceOf(StreamableMessage::class, $message);
        $this->assertNotEmpty($message->getContent());
        $this->assertEquals($expectedId, $message->getId());
        $this->assertEquals($expected, $message->getContent());
    }

    public function test_message_is_created_with_matching_hash(): void
    {
        $meta = [
            'type'    => Event::TYPE_EVENT,
            'domain'  => config('streamer.domain'),
            'name'    => 'foo.bar',
            'created' => time(),
        ];
        $data = ['foo' => 'bar'];
        $key = $meta['type'].$meta['name'].$meta['domain'].json_encode($data);
        $hash = hash('SHA256', $key);

        $message = new Message($meta, $data);
        $actual = $message->getContent();

        $this->assertNotEmpty($actual);
        $this->assertArrayHasKey('hash', $actual);
        $this->assertEquals($hash, $actual['hash']);
    }

    public function test_message_without_content_is_not_hashed(): void
    {
        $message = new class() implements StreamableMessage {
            use HashableMessage;

            protected $content = [];

            public function __construct()
            {
                $this->hashIt();
            }

            public function getContent(): array
            {
                return $this->content;
            }
        };

        $this->assertEmpty($message->getContent());
        $this->assertArrayNotHasKey('hash', $message->getContent());
    }
}
