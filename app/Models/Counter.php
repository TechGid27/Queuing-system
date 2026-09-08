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
    ];

    protected $casts = [
        'is_active' => 'boolean',
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
            ->whereDate('queue_date', today())
            ->latestOfMany();
    }
}
