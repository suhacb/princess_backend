<?php

namespace App\Models;

use App\Enums\PersonSide;
use App\Enums\ProjectRole;
use App\Traits\IsAuditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMember extends Model
{
    use IsAuditable;

    protected array $auditableFields = ['role', 'person_id'];
    protected $fillable = [
        'project_id',
        'person_id',
        'role',
        'side',
    ];

    protected $casts = [
        'role' => ProjectRole::class,
        'side' => PersonSide::class,
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
