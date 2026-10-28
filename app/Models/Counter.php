<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Counter extends Model
{
    use HasFactory;

    protected $fillable = [
        'department_id',
        'name',
        'is_active',
        'queue_paused',
        'auto_pause_enabled',
        'lunch_break_paused',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'queue_paused' => 'boolean',
        'auto_pause_enabled' => 'boolean',
        'lunch_break_paused' => 'boolean',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function queueEntries()
    {
        return $this->hasMany(QueueEntry::class);
    }

    public function currentServing()
    {
        return $this->hasOne(QueueEntry::class)
            ->where('status', 'serving')
            ->where('queue_date', today()->toDateString())
            ->latestOfMany();
    }
}
