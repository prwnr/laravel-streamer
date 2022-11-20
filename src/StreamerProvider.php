<?php

declare(strict_types=1);

namespace Prwnr\Streamer;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Prwnr\Streamer\Archiver\StorageManager;
use Prwnr\Streamer\Archiver\StreamArchiver;
use Prwnr\Streamer\Commands\Archive\ArchiveCommand;
use Prwnr\Streamer\Commands\Archive\ArchiveRestoreCommand;
use Prwnr\Streamer\Commands\Archive\PurgeCommand;
use Prwnr\Streamer\Commands\FailedMessages\FlushFailedCommand;
use Prwnr\Streamer\Commands\FailedMessages\ListFailedCommand;
use Prwnr\Streamer\Commands\FailedMessages\RetryFailedCommand;
use Prwnr\Streamer\Commands\ListCommand;
use Prwnr\Streamer\Commands\ListenCommand;
use Prwnr\Streamer\Contracts\Archiver;
use Prwnr\Streamer\Contracts\Errors\MessagesFailer;
use Prwnr\Streamer\Contracts\Errors\Repository;
use Prwnr\Streamer\Contracts\History;
use Prwnr\Streamer\Errors\FailedMessagesHandler;
use Prwnr\Streamer\Errors\MessagesRepository;
use Prwnr\Streamer\EventDispatcher\Streamer;
use Prwnr\Streamer\History\EventHistory;

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
        $this->app->bind(Archiver::class, StreamArchiver::class);

        $this->app->when(StorageManager::class)
            ->needs('$container')
            ->give(fn (): Application => $this->app);
        $this->app->singleton(StorageManager::class);

        $this->app->bind('Streamer', fn () => $this->app->make(Streamer::class));

        $this->offerPublishing();
        $this->configure();
        $this->registerCommands();

        ListenersStack::boot(config('streamer.listen_and_fire', []));
    }

    /**
     * Set up the configuration.
     */
    private function configure(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/streamer.php',
            'streamer'
        );
    }

    /**
     * Set up the resource publishing groups.
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
                ArchiveRestoreCommand::class,
                ArchiveCommand::class,
                PurgeCommand::class,
            ]);
        }
    }
}
