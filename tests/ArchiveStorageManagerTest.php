<?php

namespace Tests;

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
        $manager->extend('custom', static function () {
            return new class implements ArchiveStorage {
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

                public function delete(string $event, string $id): void
                {
                }
            };
        });

        $driver = $manager->driver('custom');
        $this->assertEquals(new Message([
            '_id' => '123',
            'type' => Event::TYPE_EVENT,
            'name' => 'foo.bar',
            'created' => time(),
        ], ['foo']), $driver->find('foo.bar', '123'));
        $this->assertNull($driver->find('bar', '123'));
    }

    public function testCustomDriverNeedsToImplementStorageContract(): void
    {
        $manager = new StorageManager($this->app);
        $manager->extend('custom', static function () {
            return new class {
            };
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(sprintf('Custom driver needs to implement [%s] interface.',
            ArchiveStorage::class));
        $manager->driver('custom')->find('123', 'resource');
    }
}