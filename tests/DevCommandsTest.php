<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\DevCommands;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use Prwnr\Streamer\ListenersStack;
use ReflectionClass;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Tests\Stubs\LocalListener;

class DevCommandsTest extends TestCase
{
    private bool $devEnabled = true;

    protected function setUp(): void
    {
        if (!class_exists(DevCommands::class)) {
            $this->markTestSkipped('DevCommands is only available on Laravel 13.16+.');
        }

        parent::setUp();

        $this->resetDevCommands();

        ListenersStack::boot([
            'foo.bar' => [LocalListener::class],
            'bar.foo' => [LocalListener::class],
        ]);
    }

    protected function tearDown(): void
    {
        if (class_exists(DevCommands::class)) {
            $this->resetDevCommands();
        }

        parent::tearDown();
    }

    #[DefineEnvironment('disableDevCommands')]
    public function test_nothing_is_registered_when_disabled(): void
    {
        $this->startCommand();

        $this->assertSame([], $this->registered());
    }

    public function test_nothing_is_registered_for_other_commands(): void
    {
        $this->startCommand('migrate');

        $this->assertSame([], $this->registered());
    }

    public function test_default_listener_listens_to_all_events(): void
    {
        $this->startCommand();

        $this->assertSame([
            'laravel-streamer' => 'php artisan streamer:listen --all --group=dev --consumer=local',
        ], $this->registered());
    }

    public function test_each_listener_is_registered_as_a_process_named_after_its_key(): void
    {
        config(['streamer.dev.listeners' => [
            'streamer-foo' => ['events' => 'foo.bar', 'group' => 'foo', 'consumer' => 'foo-1'],
            'streamer-bar' => ['events' => 'bar.foo', 'group' => 'bar', 'consumer' => 'bar-1'],
        ]]);

        $this->startCommand();

        $this->assertSame([
            'streamer-foo' => 'php artisan streamer:listen foo.bar --group=foo --consumer=foo-1',
            'streamer-bar' => 'php artisan streamer:listen bar.foo --group=bar --consumer=bar-1',
        ], $this->registered());
    }

    public function test_events_list_is_normalized(): void
    {
        config(['streamer.dev.listeners' => [
            'streamer' => ['events' => ' foo.bar , bar.foo,'],
        ]]);

        $this->startCommand();

        $this->assertSame([
            'streamer' => 'php artisan streamer:listen foo.bar,bar.foo --group=dev --consumer=local',
        ], $this->registered());
    }

    public function test_args_are_appended_to_the_command(): void
    {
        config(['streamer.dev.listeners' => [
            'streamer' => ['events' => '*', 'args' => '--purge --max-attempts=3'],
        ]]);

        $this->startCommand();

        $this->assertSame([
            'streamer' => 'php artisan streamer:listen --all --group=dev --consumer=local --purge --max-attempts=3',
        ], $this->registered());
    }

    public function test_group_and_consumer_are_escaped_when_not_plain_names(): void
    {
        config(['streamer.dev.listeners' => [
            'streamer' => ['events' => '*', 'group' => 'local dev', 'consumer' => '$(whoami)'],
        ]]);

        $this->startCommand();

        $this->assertSame([
            'streamer' => "php artisan streamer:listen --all --group='local dev' --consumer='$(whoami)'",
        ], $this->registered());
    }

    public function test_listener_without_local_listeners_is_skipped(): void
    {
        config(['streamer.dev.listeners' => [
            'streamer-foo' => ['events' => 'foo.bar'],
            'streamer-missing' => ['events' => 'missing.event'],
        ]]);

        $this->startCommand();

        $this->assertSame(['streamer-foo'], array_keys($this->registered()));
    }

    public function test_events_without_local_listeners_are_left_out_of_the_command(): void
    {
        config(['streamer.dev.listeners' => [
            'streamer' => ['events' => 'missing.event,foo.bar'],
        ]]);

        $this->startCommand();

        $this->assertSame([
            'streamer' => 'php artisan streamer:listen foo.bar --group=dev --consumer=local',
        ], $this->registered());
    }

    public function test_listening_to_all_events_is_skipped_without_local_listeners(): void
    {
        ListenersStack::boot([]);

        $this->startCommand();

        $this->assertSame([], $this->registered());
    }

    public function test_listeners_added_after_boot_are_included(): void
    {
        ListenersStack::boot([]);
        ListenersStack::add('foo.bar', LocalListener::class);

        $this->startCommand();

        $this->assertSame(['laravel-streamer'], array_keys($this->registered()));
    }

    public function test_application_can_exclude_listener_process(): void
    {
        DevCommands::except('laravel-streamer');

        $this->startCommand();

        $this->assertSame([], $this->registered());
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('streamer.dev.enabled', $this->devEnabled);
    }

    protected function disableDevCommands(Application $app): void
    {
        $this->devEnabled = false;
        $app['config']->set('streamer.dev.enabled', false);
    }

    private function startCommand(string $command = 'dev'): void
    {
        $this->app['events']->dispatch(new CommandStarting($command, new ArrayInput([]), new NullOutput()));
    }

    /**
     * @return array<string, string>
     */
    private function registered(): array
    {
        return array_column(DevCommands::commands(), 'command', 'name');
    }

    private function resetDevCommands(): void
    {
        foreach ((new ReflectionClass(DevCommands::class))->getProperties() as $property) {
            if ($property->isStatic() && $property->hasDefaultValue()) {
                $property->setValue(null, $property->getDefaultValue());
            }
        }
    }
}
