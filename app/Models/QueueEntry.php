<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QueueEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_number',
        'name',
        'purpose',
        'phone_number',
        'status',
        'served_at',
        'completed_at',
        'user_id',
        'guest_id',
        'department_id',
        'queue_date',
        'purpose_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function purposeModel()
    {
        return $this->belongsTo(Purpose::class, 'purpose_id');
    }

    protected $casts = [
        'served_at' => 'datetime',
        'completed_at' => 'datetime',
        'queue_date' => 'date',
    ];
}
