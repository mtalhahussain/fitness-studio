<?php

namespace App\Http\Requests;

use App\Models\MembershipPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMembershipPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('gym.settings');
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'type'        => ['required', Rule::in(['monthly', 'quarterly', 'yearly'])],
            'price'       => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'features'    => ['nullable', 'array'],
            'features.*'  => ['string'],
            'is_active'   => ['sometimes', 'boolean'],
        ];
    }
}
