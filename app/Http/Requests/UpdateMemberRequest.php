<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('members.edit');
    }

    public function rules(): array
    {
        return [
            'name'   => ['sometimes', 'string', 'max:255'],
            'phone'  => ['nullable', 'string', 'max:20'],
            'status' => ['sometimes', 'in:active,inactive,suspended'],
            'notes'  => ['nullable', 'string'],
        ];
    }
}
