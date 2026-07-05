<?php

namespace App\Models;

use App\Enums\RequirementPriority;
use App\Enums\RequirementStatus;
use App\Enums\RequirementType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequirementVersion extends Model
{
    use HasFactory;

    // Versions are immutable — only created_at is tracked.
    const UPDATED_AT = null;

    protected $fillable = [
        'requirement_id',
        'version_number',
        'title',
        'description',
        'type',
        'priority',
        'status',
        'role',
        'action',
        'benefit',
        'owner_id',
        'created_by',
    ];

    protected $casts = [
        'version_number' => 'integer',
        'type'           => RequirementType::class,
        'priority'       => RequirementPriority::class,
        'status'         => RequirementStatus::class,
        'created_at'     => 'datetime',
    ];

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new \LogicException('Requirement versions are immutable and cannot be modified.');
        }

        return parent::save($options);
    }

    public function delete(): bool|null
    {
        throw new \LogicException('Requirement versions are immutable and cannot be deleted.');
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(Requirement::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'owner_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'created_by');
    }
}
