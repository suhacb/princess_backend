<?php

namespace App\Models;

use App\Enums\AcceptanceCriterionDecision;
use App\Enums\AcceptanceCriterionStatus;
use App\Enums\VerificationMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class AcceptanceCriterion extends Model
{
    use HasFactory, SoftDeletes;

    /** Fields snapshotted into acceptance_criterion_versions; a version is only bumped when one of these actually changes. */
    public const VERSIONED_FIELDS = [
        'title', 'description', 'verifier_id', 'verification_method', 'status',
        'supplier_passed', 'client_passed', 'supplier_decision', 'client_decision',
    ];

    protected $fillable = [
        'project_id',
        'requirement_id',
        'ref',
        'title',
        'description',
        'measurement_method',
        'acceptance_threshold',
        'verifier_id',
        'verification_method',
        'status',
        'version',
        'approved_by',
        'approved_at',
        'supplier_passed',
        'supplier_passed_at',
        'supplier_decision',
        'supplier_decided_by',
        'supplier_decided_at',
        'supplier_decision_note',
        'client_passed',
        'client_passed_at',
        'client_decision',
        'client_decided_by',
        'client_decided_at',
        'client_decision_note',
        'accepted_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'status'              => AcceptanceCriterionStatus::class,
        'version'             => 'integer',
        'verification_method' => VerificationMethod::class,
        'approved_at'         => 'datetime',
        'supplier_passed'     => 'boolean',
        'supplier_passed_at'  => 'datetime',
        'supplier_decision'   => AcceptanceCriterionDecision::class,
        'supplier_decided_at' => 'datetime',
        'client_passed'       => 'boolean',
        'client_passed_at'    => 'datetime',
        'client_decision'     => AcceptanceCriterionDecision::class,
        'client_decided_at'   => 'datetime',
        'accepted_at'         => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(Requirement::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'verifier_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'approved_by');
    }

    public function supplierDecidedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'supplier_decided_by');
    }

    public function clientDecidedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'client_decided_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'updated_by');
    }

    public function testScenarios(): BelongsToMany
    {
        return $this->belongsToMany(
            TestScenario::class,
            'test_scenario_acceptance_criteria'
        );
    }

    public function versions(): HasMany
    {
        return $this->hasMany(AcceptanceCriterionVersion::class);
    }

    public function isDeletable(): bool
    {
        return $this->status === AcceptanceCriterionStatus::Draft
            && $this->testScenarios()->doesntExist();
    }

    public static function nextRef(int $projectId): string
    {
        $count = static::withTrashed()->where('project_id', $projectId)->count();
        return 'AC-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Applies $attributes and, only if any versioned field actually changes,
     * bumps version and snapshots it. Locks the row first so concurrent
     * writers (a controller action and the test-session recomputation path)
     * can't race to the same next version number.
     */
    public function applyVersionedChange(array $attributes, int $actingPersonId, ?\Closure $afterFill = null): void
    {
        DB::transaction(function () use ($attributes, $actingPersonId, $afterFill) {
            $locked = self::where('id', $this->id)->lockForUpdate()->firstOrFail();

            $locked->fill($attributes);
            $contentChanged = $locked->isDirty(self::VERSIONED_FIELDS);

            if ($contentChanged) {
                $locked->version += 1;
            }

            if ($afterFill) {
                $afterFill($locked);
            }

            $locked->save();

            if ($contentChanged) {
                $locked->snapshotVersion($actingPersonId);
            }
        });
    }

    /**
     * Snapshots the criterion's current versioned fields as its current version_number.
     * Also captures the decision notes as of this version — they aren't version-bump
     * triggers themselves (only supplier_decision/client_decision are), but without
     * this a decision's rationale would be lost the moment the decision changes again,
     * since the live row's note column gets overwritten.
     */
    public function snapshotVersion(int $actingPersonId): void
    {
        $fields = collect(self::VERSIONED_FIELDS)->mapWithKeys(function (string $field) {
            $value = $this->{$field};

            return [$field => $value instanceof \BackedEnum ? $value->value : $value];
        })->all();

        AcceptanceCriterionVersion::create(array_merge($fields, [
            'acceptance_criterion_id' => $this->id,
            'version_number'          => $this->version,
            'supplier_decision_note'  => $this->supplier_decision_note,
            'client_decision_note'    => $this->client_decision_note,
            'created_by'              => $actingPersonId,
        ]));
    }

    /**
     * Recomputes accepted_at from the human decision fields only — the
     * computed supplier_passed/client_passed signal never sets or clears
     * acceptance directly, so a test regression can't silently revoke a
     * human's prior sign-off.
     */
    public function recomputeAccepted(): void
    {
        $isAccepted = $this->supplier_decision === AcceptanceCriterionDecision::Accepted
            && $this->client_decision === AcceptanceCriterionDecision::Accepted;

        $this->accepted_at = $isAccepted ? ($this->accepted_at ?? now()) : null;
    }
}
