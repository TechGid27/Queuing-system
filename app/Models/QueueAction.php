<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QueueAction extends Model
{
    protected $fillable = [
        'department_id',
        'queue_entry_id',
        'actor_id',
        'action',
        'ticket_number',
        'from_status',
        'to_status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function queueEntry()
    {
        return $this->belongsTo(QueueEntry::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
