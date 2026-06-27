<?php

namespace App\Models;

use App\Enums\RiskProximity;
use App\Enums\RiskResponseType;
use App\Enums\RiskStatus;
use App\Traits\IsAuditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Risk extends Model
{
    use HasFactory, IsAuditable;

    protected array $auditableFields = [
        'title', 'description', 'category', 'probability', 'impact', 'proximity', 'status', 'response_type', 'response_action', 'risk_owner',
    ];

    protected $fillable = [
        'project_id',
        'stage_id',
        'title',
        'description',
        'category',
        'probability',
        'impact',
        'proximity',
        'risk_owner',
        'response_type',
        'response_action',
        'residual_probability',
        'residual_impact',
        'status',
        'raised_at',
    ];

    protected $casts = [
        'proximity'     => RiskProximity::class,
        'response_type' => RiskResponseType::class,
        'status'        => RiskStatus::class,
        'raised_at'     => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'risk_owner');
    }

    public function riskScore(): int
    {
        return $this->probability * $this->impact;
    }

    public function residualRiskScore(): ?int
    {
        if ($this->residual_probability === null || $this->residual_impact === null) {
            return null;
        }
        return $this->residual_probability * $this->residual_impact;
    }
}
