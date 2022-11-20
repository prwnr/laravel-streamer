<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\Concerns\InteractsWithRedis;
use Prwnr\Streamer\Concerns\ConnectsWithRedis;
use Prwnr\Streamer\Errors\MessagesRepository;

class FailedFlushCommandTest extends TestCase
{
    use InteractsWithRedis;
    use ConnectsWithRedis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRedis();
        $this->redis['phpredis']->connection()->flushall();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->redis['phpredis']->connection()->flushall();
        $this->tearDownRedis();
    }

    public function test_flushing_multiple_failed_messages(): void
    {
        $this->failFakeMessage('foo.bar', '123', ['payload' => 123]);
        $this->failFakeMessage('foo.bar', '234', ['payload' => 123]);
        $this->failFakeMessage('foo.bar', '456', ['payload' => 123]);

        $this->assertEquals(3, $this->redis()->sCard(MessagesRepository::ERRORS_SET));

        $this->artisan('streamer:failed:flush')
            ->expectsOutput('Flushed 3 messages.')
            ->assertExitCode(0);

        $this->assertEquals(0, $this->redis()->sCard(MessagesRepository::ERRORS_SET));
    }

    public function test_flushing_single_failed_message(): void
    {
        $this->failFakeMessage('foo.bar', '123', ['payload' => 123]);

        $this->assertEquals(1, $this->redis()->sCard(MessagesRepository::ERRORS_SET));

        $this->artisan('streamer:failed:flush')
            ->expectsOutput('Flushed 1 message.')
            ->assertExitCode(0);

        $this->assertEquals(0, $this->redis()->sCard(MessagesRepository::ERRORS_SET));
    }

    public function test_flushing_when_there_are_no_messages(): void
    {
        $this->assertEquals(0, $this->redis()->sCard(MessagesRepository::ERRORS_SET));

        $this->artisan('streamer:failed:flush')
            ->expectsOutput('No messages to remove.')
            ->assertExitCode(0);
    }
}
