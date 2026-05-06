<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('members.create');
    }

    public function rules(): array
    {
        return [
            'plan_id'     => ['required', 'integer', 'exists:membership_plans,id'],
            'start_date'  => ['nullable', 'date'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'notes'       => ['nullable', 'string'],
        ];
    }
}
