<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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

    public function purposes()
    {
        return $this->belongsToMany(Purpose::class)->withTimestamps();
    }

    /**
     * Purpose ids this window handles. Empty collection = general window
     * (serves every purpose).
     */
    public function purposeIds(): array
    {
        return $this->relationLoaded('purposes')
            ? $this->purposes->pluck('id')->all()
            : $this->purposes()->pluck('purposes.id')->all();
    }

    /**
     * Ids of available (active, not paused) counters in a department that take
     * tickets whose purpose is "Other" or assigned to no available window. A
     * paused window's purposes therefore fall to the remaining windows until it
     * resumes. General windows (no purposes)
     * take them when present; otherwise the windows with the fewest
     * assigned purposes do.
     */
    public static function overflowIds(int $departmentId): array
    {
        $counters = static::where('department_id', $departmentId)
            ->where('is_active', true)
            ->where('queue_paused', false)
            ->with('purposes')
            ->get();

        $general = $counters->filter(fn ($c) => $c->purposes->isEmpty());
        if ($general->isNotEmpty()) {
            return $general->pluck('id')->all();
        }

        $min = $counters->map(fn ($c) => $c->purposes->count())->min();

        return $counters->filter(fn ($c) => $c->purposes->count() === $min)
            ->pluck('id')
            ->all();
    }

    /**
     * Purpose ids assigned to at least one available (active, not paused)
     * counter in the department.
     */
    public static function assignedPurposeIds(int $departmentId): array
    {
        return DB::table('counter_purpose')
            ->join('counters', 'counters.id', '=', 'counter_purpose.counter_id')
            ->where('counters.department_id', $departmentId)
            ->where('counters.is_active', true)
            ->where('counters.queue_paused', false)
            ->pluck('counter_purpose.purpose_id')
            ->unique()
            ->values()
            ->all();
    }
}
