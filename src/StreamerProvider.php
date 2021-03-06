<?php

namespace Prwnr\Streamer;

use Illuminate\Support\ServiceProvider;
use Prwnr\Streamer\Commands\FlushFailedCommand;
use Prwnr\Streamer\Commands\ListCommand;
use Prwnr\Streamer\Commands\ListenCommand;
use Prwnr\Streamer\Commands\ListFailedCommand;
use Prwnr\Streamer\Commands\RetryFailedCommand;
use Prwnr\Streamer\Contracts\Errors\MessagesFailer;
use Prwnr\Streamer\Contracts\Errors\Repository;
use Prwnr\Streamer\Contracts\History;
use Prwnr\Streamer\Errors\FailedMessagesHandler;
use Prwnr\Streamer\Errors\MessagesRepository;
use Prwnr\Streamer\EventDispatcher\Streamer;
use Prwnr\Streamer\History\EventHistory;

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
        $this->app->bind(History::class, EventHistory::class);
        $this->app->bind(MessagesFailer::class, FailedMessagesHandler::class);
        $this->app->bind(Repository::class, MessagesRepository::class);

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
                ListCommand::class,
                ListFailedCommand::class,
                RetryFailedCommand::class,
                FlushFailedCommand::class,
            ]);
        }
    }
}
