<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Guest extends Authenticatable
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'phone_number',
        'phone_verified_at',
        'role',
    ];

    protected $hidden = [
        'remember_token',
    ];

    protected $casts = [
        'phone_verified_at' => 'datetime',
    ];

    public function isPhoneVerified(): bool
    {
        return $this->phone_verified_at !== null;
    }

    public function queueEntries()
    {
        return $this->hasMany(QueueEntry::class);
    }
}
