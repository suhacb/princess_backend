<?php

namespace App\Contracts;

use App\Models\Project;
use DateTimeInterface;

interface DocumentStorageDriver
{
    /**
     * Store file contents at the given key within the project's storage.
     * $contents may be a string or a resource stream.
     */
    public function put(Project $project, string $key, mixed $contents): void;

    /** Retrieve raw file contents for the given key. */
    public function get(Project $project, string $key): string;

    /**
     * Generate a short-lived URL for direct client download.
     * The URL must be reachable from the end-user's browser, not just the server.
     */
    public function temporaryUrl(Project $project, string $key, DateTimeInterface $expiry): string;

    /** Delete the file at the given key. */
    public function delete(Project $project, string $key): void;

    /** Returns true if a file exists at the given key. */
    public function exists(Project $project, string $key): bool;

    /** Copy a file within the same project's storage (e.g. for version revert). */
    public function copy(Project $project, string $sourceKey, string $destKey): void;

    /** Returns the file size in bytes. */
    public function size(Project $project, string $key): int;
}
