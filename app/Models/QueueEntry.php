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
        'purpose_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function purposeModel()
    {
        return $this->belongsTo(Purpose::class, 'purpose_id');
    }

    protected $casts = [
        'served_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
