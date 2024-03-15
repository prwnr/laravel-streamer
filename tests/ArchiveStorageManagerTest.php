<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Support\Collection;
use Prwnr\Streamer\Archiver\NullStorage;
use Prwnr\Streamer\Archiver\StorageManager;
use Prwnr\Streamer\Contracts\ArchiveStorage;
use Prwnr\Streamer\Contracts\Event;
use Prwnr\Streamer\EventDispatcher\Message;
use RuntimeException;

class ArchiveStorageManagerTest extends TestCase
{
    public function test_default_driver(): void
    {
        $manager = new StorageManager($this->app);

        $this->assertEquals('null', $manager->getDefaultDriver());

        $driver = $manager->createNullDriver();
        $this->assertInstanceOf(NullStorage::class, $driver);
        $this->assertNull($driver->find('', ''));
    }

    public function test_custom_manager_driver(): void
    {
        $manager = new StorageManager($this->app);
        $manager->extend(
            'custom',
            static fn (): ArchiveStorage => new class () implements ArchiveStorage {
                public function create(Message $message): void
                {
                }

                public function find(string $event, string $id): ?Message
                {
                    if ($event !== 'foo.bar') {
                        return null;
                    }

                    return new Message([
                        '_id' => '123',
                        'type' => Event::TYPE_EVENT,
                        'name' => 'foo.bar',
                        'created' => time(),
                    ], ['foo']);
                }

                public function findMany(string $event): Collection
                {
                    if ($event !== 'foo.bar') {
                        return collect();
                    }

                    return collect([
                        new Message([
                            '_id' => '123',
                            'type' => Event::TYPE_EVENT,
                            'name' => 'foo.bar',
                            'created' => time(),
                        ], ['foo']),
                    ]);
                }

                public function all(): Collection
                {
                    return collect([
                        new Message([
                            '_id' => '123',
                            'type' => Event::TYPE_EVENT,
                            'name' => 'foo.bar',
                            'created' => time(),
                        ], ['foo']),
                    ]);
                }

                public function delete(string $event, string $id = null): int
                {
                    if ($event === 'foo.bar' && $id === '123') {
                        return 1;
                    }
                    if ($event === 'foo.bar') {
                        return 2;
                    }
                    return 0;
                }
            }
        );

        $driver = $manager->driver('custom');

        $this->assertCount(1, $driver->all());
        $this->assertCount(1, $driver->findMany('foo.bar'));
        $this->assertEquals(new Message([
            '_id' => '123',
            'type' => Event::TYPE_EVENT,
            'name' => 'foo.bar',
            'created' => time(),
        ], ['foo']), $driver->find('foo.bar', '123'));

        $this->assertNull($driver->find('bar', '123'));
        $this->assertCount(0, $driver->findMany('foo'));

        $this->assertEquals(1, $driver->delete('foo.bar', '123'));
        $this->assertEquals(0, $driver->delete('foo', '123'));
        $this->assertEquals(2, $driver->delete('foo.bar'));
    }

    public function testCustomDriverNeedsToImplementStorageContract(): void
    {
        $manager = new StorageManager($this->app);
        $manager->extend('custom', static fn (): object => new class () {
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Custom driver needs to implement [%s] interface.',
                ArchiveStorage::class
            )
        );
        $manager->driver('custom')->find('123', 'resource');
    }
}
