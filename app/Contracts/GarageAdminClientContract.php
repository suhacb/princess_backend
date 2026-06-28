<?php

namespace App\Contracts;

interface GarageAdminClientContract
{
    public function ping(): bool;

    /** Returns the ID of the first available cluster node. */
    public function getNodeId(): string;

    /** Returns the current committed layout version. */
    public function getLayoutVersion(): int;

    /** Returns true if the node already has a zone/capacity role assigned. */
    public function nodeHasRole(string $nodeId): bool;

    /**
     * Stages and applies a single-node layout.
     * No-ops if the node already has an identical role.
     */
    public function applyLayout(string $nodeId, string $zone, int $capacityBytes): void;

    /**
     * Creates a new access key. Returns ['accessKeyId' => ..., 'secretAccessKey' => ...].
     * Throws RuntimeException on failure.
     */
    public function createKey(string $name): array;

    /** Returns the key row ['accessKeyId' => ..., 'name' => ...] or null if not found. */
    public function findKey(string $name): ?array;

    /**
     * Creates a bucket with a global alias. Returns the bucket ID.
     * Throws RuntimeException on failure.
     */
    public function createBucket(string $globalAlias): string;

    /** Returns the bucket ID for the given global alias, or null if not found. */
    public function findBucket(string $globalAlias): ?string;

    /** Grants read + write + owner permissions for the key on the bucket. */
    public function allowKeyOnBucket(string $bucketId, string $keyId): void;

    /** Returns all buckets as [['id' => ..., 'globalAliases' => [...]], ...]. */
    public function listBuckets(): array;

    /** Returns buckets whose global alias starts with the given prefix. */
    public function listBucketsWithPrefix(string $prefix): array;

    /**
     * Deletes a bucket. The bucket must be empty.
     * Throws RuntimeException on failure.
     */
    public function deleteBucket(string $bucketId): void;
}
