<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignMemberToTrainerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('trainers.edit');
    }

    public function rules(): array
    {
        return [
            'member_id' => ['required', 'integer', 'exists:users,id'],
            'notes'     => ['nullable', 'string'],
        ];
    }
}
