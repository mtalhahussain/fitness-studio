<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth check handled in controller
    }

    public function rules(): array
    {
        return [
            'user_id'    => ['nullable', 'integer', 'exists:users,id'],
            'check_in_time' => ['nullable', 'date'],
        ];
    }
}
