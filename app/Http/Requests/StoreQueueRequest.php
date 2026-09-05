<?php

namespace App\Http\Requests;

use App\Models\QueueEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreQueueRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'department_id' => 'required|integer|exists:departments,id',
            'purpose_id' => 'required',
            'other_purpose' => 'nullable|string|max:255',
        ];
    }

    /**
     * Prevent a student from joining the queue if they already have
     * an active (waiting or serving) ticket today.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $guest = Auth::guard('student')->user();

            if (! $guest) {
                return;
            }

            $department = \App\Models\Department::active()->find($this->integer('department_id'));
            if (! $department) {
                $validator->errors()->add('department_id', 'Please select an active department.');

                return;
            }

            if ($department->queue_paused) {
                $validator->errors()->add('department_id', 'This department queue is currently paused.');
            }

            // Validate purpose_id: must be "other" or an existing active purpose
            $purposeId = $this->input('purpose_id');
            if ($purposeId !== 'other') {
                $exists = \App\Models\Purpose::where('id', $purposeId)
                    ->where('is_active', true)
                    ->exists();
                if (! $exists) {
                    $validator->errors()->add('purpose_id', 'Please select a valid purpose.');
                }
            } else {
                // "Other" requires the text field
                if (! trim($this->input('other_purpose', ''))) {
                    $validator->errors()->add('purpose_id', 'Please describe your purpose of visit.');
                }
            }

            $activeTicket = QueueEntry::where('guest_id', $guest->id)
                ->whereDate('queue_date', today())
                ->whereIn('status', ['waiting', 'serving'])
                ->exists();

            if ($activeTicket) {
                $validator->errors()->add('purpose_id', 'You already have an active ticket in the queue.');
            }

            // Queue capacity limit (default: 50 students max)
            $maxCapacity = (int) config('queue_system.max_capacity', 50);
            $currentCount = QueueEntry::where('department_id', $department->id)
                ->whereDate('queue_date', today())
                ->whereIn('status', ['waiting', 'serving'])
                ->count();

            if ($maxCapacity > 0 && $currentCount >= $maxCapacity) {
                $validator->errors()->add('purpose_id', "The queue is currently full (max {$maxCapacity} students). Please try again later.");
            }
        });
    }
}
