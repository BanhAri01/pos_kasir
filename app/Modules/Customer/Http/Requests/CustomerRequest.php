<?php

namespace App\Modules\Customer\Http\Requests;

use App\Core\Support\Phone;
use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::ManageCustomers->value);
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('phone')) {
            $this->merge(['phone' => Phone::normalize($this->input('phone')) ?? $this->input('phone')]);
        }
    }

    public function rules(): array
    {
        return [
            'uuid' => ['nullable', 'uuid'],
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'regex:/^628\d{7,12}$/'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'price_level_id' => ['sometimes', 'nullable', 'integer', Rule::exists('price_levels', 'id')->where('tenant_id', $this->user()->tenant_id)],
            'credit_limit' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama pelanggan belum diisi.',
            'phone.regex' => 'No HP sepertinya salah. Contoh yang benar: 0812 3456 7890.',
        ];
    }
}
