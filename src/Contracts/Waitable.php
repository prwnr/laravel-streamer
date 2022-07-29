<?php

namespace Prwnr\Streamer\Contracts;

/**
 * Interface Waitable.
 * @deprecated will be removed with next major version
 */
interface Waitable
{
    /**
     * Get Waitable stream name.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get key name for new entries, which is different depending on what is used.
     *
     * @return string
     */
    public function getNewEntriesKey(): string;

    /**
     * Awaits for a new message starting from last seen ID
     * ID can be a new entry key.
     *
     * @param string $lastSeenId
     * @param int    $timeout
     *
     * @return array|null
     */
    public function await(string $lastSeenId, int $timeout = 0): ?array;

    /**
     * Acknowledge message on stream by ID.
     *
     * @param string $id
     */
    public function acknowledge(string $id): void;
}
