<?php

namespace App\Services\Document;

use App\Contracts\DocumentStorageDriver;
use App\Models\Project;
use DateTimeInterface;

/**
 * M365 (SharePoint) storage driver stub.
 *
 * Data compatibility contract for future implementers:
 *   - Keys follow the convention `m365://{driveId}/{itemId}` so that
 *     the `document_versions.s3_key` column is meaningful for both drivers.
 *   - `temporaryUrl()` returns a time-limited SharePoint sharing link.
 *   - `put()` and `get()` use the Microsoft Graph drive items API.
 *   - `copy()` uses the Graph copy endpoint (async; poll for completion).
 */
class M365StorageService implements DocumentStorageDriver
{
    public function put(Project $project, string $key, mixed $contents): void
    {
        throw new \RuntimeException('M365 storage driver not yet implemented.');
    }

    public function get(Project $project, string $key): string
    {
        throw new \RuntimeException('M365 storage driver not yet implemented.');
    }

    public function temporaryUrl(Project $project, string $key, DateTimeInterface $expiry): string
    {
        throw new \RuntimeException('M365 storage driver not yet implemented.');
    }

    public function delete(Project $project, string $key): void
    {
        throw new \RuntimeException('M365 storage driver not yet implemented.');
    }

    public function exists(Project $project, string $key): bool
    {
        throw new \RuntimeException('M365 storage driver not yet implemented.');
    }

    public function copy(Project $project, string $sourceKey, string $destKey): void
    {
        throw new \RuntimeException('M365 storage driver not yet implemented.');
    }

    public function size(Project $project, string $key): int
    {
        throw new \RuntimeException('M365 storage driver not yet implemented.');
    }
}
