<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentVersion extends Model
{
    use HasFactory;

    // Versions are immutable — only created_at is tracked.
    const UPDATED_AT = null;

    protected $fillable = [
        'document_id',
        'version_number',
        's3_key',
        'file_name',
        'file_size_bytes',
        'onlyoffice_key',
        'converted_md_key',
        'comment',
        'created_by',
    ];

    protected $casts = [
        'version_number'          => 'integer',
        'file_size_bytes'         => 'integer',
        'closed_without_changes'  => 'boolean',
        'last_active_at'          => 'datetime',
        'created_at'              => 'datetime',
    ];

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new \LogicException('Document versions are immutable and cannot be modified.');
        }

        return parent::save($options);
    }

    public function delete(): bool|null
    {
        throw new \LogicException('Document versions are immutable and cannot be deleted.');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(QaDocument::class, 'document_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'created_by');
    }
}
