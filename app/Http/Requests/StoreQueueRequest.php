<?php

namespace App\Http\Requests;

use App\Models\Purpose;
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
     * Only purpose-specific rules live here. Department status, duplicate
     * tickets, and capacity are validated once inside the locked transaction
     * in QueueController::store() to avoid duplicate queries and races.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (! Auth::guard('student')->user()) {
                return;
            }

            $purposeId = $this->input('purpose_id');

            if ($purposeId === 'other') {
                if (! trim($this->input('other_purpose', ''))) {
                    $validator->errors()->add('purpose_id', 'Please describe your purpose of visit.');
                }

                return;
            }

            $exists = Purpose::where('id', $purposeId)
                ->where('is_active', true)
                ->exists();

            if (! $exists) {
                $validator->errors()->add('purpose_id', 'Please select a valid purpose.');
            }
        });
    }
}
