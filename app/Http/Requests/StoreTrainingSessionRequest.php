<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTrainingSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('schedule.manage');
    }

    public function rules(): array
    {
        return [
            'title'         => ['required', 'string', 'max:255'],
            'member_id'     => ['nullable', 'integer', 'exists:users,id'],
            'scheduled_at'  => ['required', 'date', 'after:now'],
            'duration_mins' => ['nullable', 'integer', 'min:15', 'max:480'],
            'session_type'  => ['nullable', Rule::in(['personal', 'group'])],
            'notes'         => ['nullable', 'string'],
        ];
    }
}
