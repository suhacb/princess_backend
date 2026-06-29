<?php

namespace App\Models;

use App\Enums\DocumentCategory;
use App\Enums\QaDocumentStatus;
use App\Enums\QaDocumentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class QaDocument extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'type',
        'category',
        'title',
        'version',
        'description',
        'metadata',
        'documentable_type',
        'documentable_id',
        'status',
        'supersedes_id',
        'review_notes',
        'reviewed_by',
        'reviewed_at',
        'confirmed_by',
        'confirmed_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'type'         => QaDocumentType::class,
        'category'     => DocumentCategory::class,
        'metadata'     => 'array',
        'status'       => QaDocumentStatus::class,
        'reviewed_at'  => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (QaDocument $document) {
            if ($document->type !== null && ($document->isDirty('type') || $document->category === null)) {
                $document->category = $document->type->category();
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(QaDocument::class, 'supersedes_id');
    }

    public function supersededBy(): HasMany
    {
        return $this->hasMany(QaDocument::class, 'supersedes_id');
    }

    public function requirements(): BelongsToMany
    {
        return $this->belongsToMany(Requirement::class, 'qa_document_requirements');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'reviewed_by');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'confirmed_by');
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
        return $this->status === QaDocumentStatus::Draft;
    }
}
