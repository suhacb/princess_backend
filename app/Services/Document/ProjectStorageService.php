<?php

namespace App\Services\Document;

use App\Contracts\GarageAdminClientContract;
use App\Models\Project;

class ProjectStorageService
{
    public function __construct(
        private readonly GarageAdminClientContract $garage,
    ) {}

    public function bucketName(Project $project): string
    {
        return config('princess.garage.bucket_prefix') . '-' . $project->id;
    }

    /**
     * Provision storage for a newly created project.
     * Creates the project bucket and grants the backend key full access.
     * Idempotent — safe to call if the bucket already exists.
     *
     * Throws RuntimeException if Garage is unreachable.
     */
    public function provision(Project $project): void
    {
        $name = $this->bucketName($project);

        $bucketId = $this->garage->findBucket($name)
            ?? $this->garage->createBucket($name);

        $this->garage->allowKeyOnBucket(
            $bucketId,
            config('princess.garage.access_key_id'),
        );
    }

    /**
     * Archive storage for a closed project.
     * Currently a no-op — physical deletion is a deliberate admin action,
     * not automatic on project close. Implement when a retention policy
     * is defined (e.g. move objects to cold storage, revoke key access).
     */
    public function archive(Project $project): void
    {
        // no-op
    }
}
