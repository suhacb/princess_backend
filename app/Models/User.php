<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'person_id',
        'external_id',
        'username',
        'email',
        'name',
        'fname',
        'lname',
    ];

    protected $hidden = [
        'remember_token',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
