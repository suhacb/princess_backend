<?php

namespace App\Models;

use App\Enums\QualityMethod;
use App\Enums\QualityResult;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QualityRegisterEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'stage_id',
        'product_name',
        'quality_method',
        'planned_date',
        'actual_date',
        'reviewers',
        'result',
        'issues_raised',
        'sign_off_by',
        'sign_off_at',
    ];

    protected $casts = [
        'quality_method' => QualityMethod::class,
        'result'         => QualityResult::class,
        'planned_date'   => 'date',
        'actual_date'    => 'date',
        'reviewers'      => 'array',
        'sign_off_at'    => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    public function signedOffBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'sign_off_by');
    }
}
