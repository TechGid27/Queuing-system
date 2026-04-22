<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\QueueEntry;

class StoreQueueRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'purpose_id' => 'required|exists:purposes,id',
        ];
    }

    /**
     * Prevent a student from joining the queue if they already have
     * an active (waiting or serving) ticket today.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $user = auth()->user();

            if (! $user) {
                return;
            }

            $activeTicket = QueueEntry::where('user_id', $user->id)
                ->whereIn('status', ['waiting', 'serving'])
                ->exists();

            if ($activeTicket) {
                $validator->errors()->add('purpose_id', 'You already have an active ticket in the queue.');
            }

            // Queue capacity limit (default: 50 students max)
            $maxCapacity  = (int) config('queue_system.max_capacity', 50);
            $currentCount = QueueEntry::whereIn('status', ['waiting', 'serving'])->count();

            if ($currentCount >= $maxCapacity) {
                $validator->errors()->add('purpose_id', "The queue is currently full (max {$maxCapacity} students). Please try again later.");
            }
        });
    }
}
