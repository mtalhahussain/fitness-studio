<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('members.create');
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'email'       => ['required', 'email', 'unique:users,email'],
            'phone'       => ['nullable', 'string', 'max:20'],
            'password'    => ['nullable', 'string', 'min:8'],
            'plan_id'     => ['nullable', 'integer', 'exists:membership_plans,id'],
            'start_date'  => ['nullable', 'date'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'notes'       => ['nullable', 'string'],
        ];
    }
}
