<?php

declare(strict_types=1);

namespace Tests;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\Concerns\InteractsWithRedis;

class FailedListCommandTest extends TestCase
{
    use InteractsWithRedis;

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

    public function test_lists_all_failed_messages(): void
    {
        $this->createTwoMessages();

        $this->artisan('streamer:failed:list')
            ->expectsOutput('+-----+-----------+---------------------------+-------+---------------------+')
            ->expectsOutput('| ID  | Stream    | Receiver                  | Error | Date                |')
            ->expectsOutput('+-----+-----------+---------------------------+-------+---------------------+')
            ->expectsOutput('| 123 | foo.bar   | Tests\Stubs\LocalListener | error | 2021-12-12 12:12:12 |')
            ->expectsOutput('| 321 | other.bar | Tests\Stubs\LocalListener | error | 2021-12-12 12:15:12 |')
            ->expectsOutput('+-----+-----------+---------------------------+-------+---------------------+')
            ->assertExitCode(0);
    }

    public function test_lists_all_failed_messages_in_a_compact_format(): void
    {
        $this->createTwoMessages();

        $this->artisan('streamer:failed:list', ['--compact' => true])
            ->expectsOutput('+-----+-----------+-------+')
            ->expectsOutput('| ID  | Stream    | Error |')
            ->expectsOutput('+-----+-----------+-------+')
            ->expectsOutput('| 123 | foo.bar   | error |')
            ->expectsOutput('| 321 | other.bar | error |')
            ->expectsOutput('+-----+-----------+-------+')
            ->assertExitCode(0);
    }

    public function test_not_listing_messages_if_none_exists(): void
    {
        $this->artisan('streamer:failed:list')
            ->expectsOutput('No failed messages')
            ->assertExitCode(0);
    }

    protected function createTwoMessages(): void
    {
        Carbon::withTestNow(Carbon::parse('2021-12-12 12:12:12'), function (): void {
            $this->failFakeMessage('foo.bar', '123', ['payload' => 123]);
        });
        Carbon::withTestNow(Carbon::parse('2021-12-12 12:15:12'), function (): void {
            $this->failFakeMessage('other.bar', '321', ['payload' => 321]);
        });
    }
}
