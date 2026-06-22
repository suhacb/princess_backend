<?php

namespace App\Models;

use App\Enums\IssueStatus;
use App\Enums\IssueType;
use App\Enums\IssuePriority;
use App\Enums\TeamType;
use App\Enums\TestResultStatus;
use App\Enums\TestSessionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TestSession extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'test_session_plan_id',
        'ref',
        'title',
        'session_date',
        'tester_id',
        'team_type',
        'environment',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'team_type'    => TeamType::class,
        'status'       => TestSessionStatus::class,
        'session_date' => 'date',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(TestSessionPlan::class, 'test_session_plan_id');
    }

    public function tester(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'tester_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(TestSessionResult::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'updated_by');
    }

    public function isDeletable(): bool
    {
        return $this->status === TestSessionStatus::Planned;
    }

    public static function nextRef(int $projectId): string
    {
        $count = static::withTrashed()->where('project_id', $projectId)->count();
        return 'TSR-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
    }

    public function recomputeAcStatus(): void
    {
        $affectedAcIds = $this->results()
            ->with('testScenario.acceptanceCriteria')
            ->get()
            ->flatMap(fn ($r) => $r->testScenario->acceptanceCriteria)
            ->pluck('id')
            ->unique();

        foreach (AcceptanceCriterion::whereIn('id', $affectedAcIds)->get() as $ac) {
            $scenarios = $ac->testScenarios()->get();

            $supplierPassed = $scenarios->isNotEmpty() && $scenarios->every(
                fn ($s) => $s->latestResultForTeam('supplier') === TestResultStatus::Pass->value
            );
            $clientPassed = $scenarios->isNotEmpty() && $scenarios->every(
                fn ($s) => $s->latestResultForTeam('client') === TestResultStatus::Pass->value
            );

            $isAccepted  = $supplierPassed && $clientPassed;
            $wasAccepted = $ac->accepted_at !== null;

            $ac->update([
                'supplier_passed' => $supplierPassed,
                'client_passed'   => $clientPassed,
                'accepted_at'     => $isAccepted && ! $wasAccepted ? now() : ($isAccepted ? $ac->accepted_at : null),
            ]);
        }
    }

    public function createIssuesForFailures(): void
    {
        $failResults = $this->results()
            ->with('testScenario')
            ->where('result', TestResultStatus::Fail->value)
            ->get();

        foreach ($failResults as $result) {
            $description = "Test failure during session '{$this->title}' (ref: {$this->ref}).\n"
                . "Scenario: {$result->testScenario->ref} — {$result->testScenario->title}.\n";

            if ($result->defect_ref) {
                $description .= "Defect ref: {$result->defect_ref}.\n";
            }
            if ($result->notes) {
                $description .= "Notes: {$result->notes}";
            }

            Issue::create([
                'project_id'  => $this->project_id,
                'issue_type'  => IssueType::OffSpec->value,
                'title'       => "Test failure: {$result->testScenario->title}",
                'description' => $description,
                'raised_by'   => $this->tester_id,
                'raised_at'   => now(),
                'priority'    => IssuePriority::Medium->value,
                'status'      => IssueStatus::Open->value,
            ]);
        }
    }
}
