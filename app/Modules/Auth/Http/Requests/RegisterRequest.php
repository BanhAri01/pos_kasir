<?php

namespace App\Modules\Auth\Http\Requests;

use App\Core\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => Phone::normalize($this->input('phone')) ?? $this->input('phone'),
        ]);
    }

    public function rules(): array
    {
        return [
            'business_type' => ['required', Rule::exists('business_types', 'code')->where('is_active', true)],
            'business_name' => ['required', 'string', 'max:100'],
            'owner_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'regex:/^628\d{7,12}$/', Rule::unique('users', 'phone')],
            'password' => ['required', 'string', 'min:6', 'max:100'],
            'ref' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'business_type.required' => 'Pilih dulu jenis usaha Anda.',
            'business_type.exists' => 'Jenis usaha tidak ditemukan. Silakan pilih lagi.',
            'business_name.required' => 'Nama usaha belum diisi.',
            'owner_name.required' => 'Nama Anda belum diisi.',
            'phone.required' => 'No HP belum diisi.',
            'phone.regex' => 'No HP sepertinya salah. Contoh yang benar: 0812 3456 7890.',
            'phone.unique' => 'No HP ini sudah terdaftar. Silakan masuk, atau pakai no HP lain.',
            'password.required' => 'Kata sandi belum diisi.',
            'password.min' => 'Kata sandi minimal 6 huruf atau angka.',
        ];
    }
}
