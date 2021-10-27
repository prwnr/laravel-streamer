<?php

namespace Prwnr\Streamer\Archiver;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Manager;
use Prwnr\Streamer\Contracts\ArchiveStorage;
use Prwnr\Streamer\EventDispatcher\Message;
use RuntimeException;

/**
 * Class StorageManager
 *
 * @mixin ArchiveStorage
 * @method void create(Message $message)
 * @method null|Message find(string $event, string $id)
 * @method Collection|Message[] findMany(string $event)
 * @method Collection|Message[] all()
 * @method void delete(string $event, string $id)
 */
class StorageManager extends Manager
{
    /**
     * Create null driver
     *
     * @return ArchiveStorage
     * @throws BindingResolutionException
     */
    public function createNullDriver(): ArchiveStorage
    {
        return $this->container->make(NullStorage::class);
    }

    /**
     * @inheritDoc
     */
    public function getDefaultDriver(): string
    {
        return 'null';
    }

    /**
     * @inheritDoc
     */
    protected function callCustomCreator($driver)
    {
        $custom = $this->customCreators[$driver]($this->container);
        if (!$custom instanceof ArchiveStorage) {
            $message = sprintf('Custom driver needs to implement [%s] interface.', ArchiveStorage::class);
            throw new RuntimeException($message);
        }

        return $custom;
    }
}