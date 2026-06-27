<?php

namespace App\Traits;

use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

trait IsAuditable
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->auditableFields ?? [])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function beforeActivityLogged(Activity $activity): void
    {
        $activity->properties = $activity->properties->put('project_id', $this->resolveProjectId());
    }

    protected function resolveProjectId(): ?int
    {
        return $this->project_id ?? null;
    }
}
