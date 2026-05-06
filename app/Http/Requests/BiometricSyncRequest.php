<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BiometricSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'logs'                    => ['required', 'array', 'min:1'],
            'logs.*.device_user_id'   => ['required', 'string'],
            'logs.*.punch_time'       => ['required', 'date'],
            'logs.*.punch_type'       => ['nullable', 'integer', 'in:0,1'],
        ];
    }

    public function messages(): array
    {
        return [
            'logs.required'                 => 'Attendance logs array is required.',
            'logs.*.device_user_id.required' => 'Each log must include a device_user_id.',
            'logs.*.punch_time.required'     => 'Each log must include a punch_time.',
        ];
    }
}
