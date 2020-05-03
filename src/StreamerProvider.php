<?php

namespace Prwnr\Streamer;

use Illuminate\Support\ServiceProvider;
use Prwnr\Streamer\Commands\ListenCommand;
use Prwnr\Streamer\Contracts\Replayer;
use Prwnr\Streamer\EventDispatcher\Streamer;
use Prwnr\Streamer\History\Recorder;

/**
 * Class StreamerProvider.
 */
class StreamerProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(Replayer::class, Recorder::class);

        $this->app->bind('Streamer', function () {
            return $this->app->make(Streamer::class);
        });

        $this->offerPublishing();
        $this->configure();
        $this->registerCommands();

        ListenersStack::boot(config('streamer.listen_and_fire', []));
    }
    /**
     * Setup the configuration.
     *
     * @return void
     */
    private function configure(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/streamer.php', 'streamer'
        );
    }

    /**
     * Setup the resource publishing groups.
     *
     * @return void
     */
    private function offerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/streamer.php' => app()->basePath('config/streamer.php'),
            ], 'config');
        }
    }

    /**
     * Register the Artisan commands.
     */
    private function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ListenCommand::class,
            ]);
        }
    }
}
