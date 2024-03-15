<?php

declare(strict_types=1);

namespace Tests;

use Prwnr\Streamer\EventDispatcher\Message;

class ArchiveTestCase extends TestCase
{
    use WithMemoryManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpMemoryManager();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->manager = null;
    }

    protected function addArchiveMessage(string $stream, string $id, array $data): Message
    {
        $message = new Message([
            '_id' => $id,
            'name' => $stream,
        ], $data);

        $this->manager->driver('memory')->create($message);

        return $message;
    }
}
