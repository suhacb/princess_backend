<?php

namespace App\Models;

use App\Enums\PersonSide;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Person extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'organization',
        'side',
        'job_title',
        'notes',
    ];

    protected $casts = [
        'side' => PersonSide::class,
    ];

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function projectMemberships(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }
}
