<?php

namespace App\Models;

use App\Enums\AcceptanceCriterionDecision;
use App\Enums\AcceptanceCriterionStatus;
use App\Enums\VerificationMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcceptanceCriterionVersion extends Model
{
    use HasFactory;

    // Versions are immutable — only created_at is tracked.
    const UPDATED_AT = null;

    protected $fillable = [
        'acceptance_criterion_id',
        'version_number',
        'title',
        'description',
        'verifier_id',
        'verification_method',
        'status',
        'supplier_passed',
        'client_passed',
        'supplier_decision',
        'client_decision',
        'created_by',
    ];

    protected $casts = [
        'version_number'      => 'integer',
        'verification_method' => VerificationMethod::class,
        'status'              => AcceptanceCriterionStatus::class,
        'supplier_passed'     => 'boolean',
        'client_passed'       => 'boolean',
        'supplier_decision'   => AcceptanceCriterionDecision::class,
        'client_decision'     => AcceptanceCriterionDecision::class,
        'created_at'          => 'datetime',
    ];

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new \LogicException('Acceptance criterion versions are immutable and cannot be modified.');
        }

        return parent::save($options);
    }

    public function delete(): bool|null
    {
        throw new \LogicException('Acceptance criterion versions are immutable and cannot be deleted.');
    }

    public function acceptanceCriterion(): BelongsTo
    {
        return $this->belongsTo(AcceptanceCriterion::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'verifier_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'created_by');
    }
}
