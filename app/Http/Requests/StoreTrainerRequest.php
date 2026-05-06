<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTrainerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('trainers.create');
    }

    public function rules(): array
    {
        return [
            'name'             => ['required', 'string', 'max:255'],
            'email'            => ['required', 'email', 'unique:users,email'],
            'phone'            => ['nullable', 'string', 'max:20'],
            'password'         => ['nullable', 'string', 'min:8'],
            'specialization'   => ['required', 'string', 'max:255'],
            'bio'              => ['nullable', 'string'],
            'experience_years' => ['nullable', 'integer', 'min:0', 'max:50'],
            'certifications'   => ['nullable', 'array'],
            'certifications.*' => ['string'],
            'hourly_rate'      => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
