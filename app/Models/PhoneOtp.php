<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class PhoneOtp extends Model
{
    protected $fillable = ['phone_number', 'purpose', 'otp', 'expires_at'];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return now()->isAfter($this->expires_at);
    }

    public function matches(string $code): bool
    {
        return Hash::check($code, $this->otp);
    }
}
