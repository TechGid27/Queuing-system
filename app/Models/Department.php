<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'is_active',
        'queue_paused',
        'lunch_break_paused',
        'auto_pause_enabled',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'queue_paused' => 'boolean',
        'lunch_break_paused' => 'boolean',
        'auto_pause_enabled' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function staff()
    {
        return $this->hasMany(User::class)->where('role', 'staff');
    }

    public function counters()
    {
        return $this->hasMany(Counter::class)->orderBy('name');
    }

    public function activeCounters()
    {
        return $this->hasMany(Counter::class)->where('is_active', true)->orderBy('name');
    }

    public function queueEntries()
    {
        return $this->hasMany(QueueEntry::class);
    }
}
