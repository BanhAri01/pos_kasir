<?php

namespace App\Modules\Auth\Http\Requests;

use App\Core\Support\Phone;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['phone' => Phone::normalize($this->input('phone')) ?? $this->input('phone')]);
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'No HP belum diisi.',
            'password.required' => 'Kata sandi belum diisi.',
        ];
    }

    /** Cek no HP + kata sandi, dengan batas percobaan. */
    public function authenticateUser(): User
    {
        $key = 'login:'.$this->input('phone').'|'.$this->ip();

        if (RateLimiter::tooManyAttempts($key, config('hermes.max_login_attempts'))) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'phone' => "Terlalu banyak percobaan. Tunggu {$seconds} detik lalu coba lagi.",
            ]);
        }

        $user = User::query()->where('phone', $this->input('phone'))->first();

        if (! $user || ! $user->password || ! Hash::check($this->input('password'), $user->password)) {
            RateLimiter::hit($key, config('hermes.login_lockout_seconds'));

            throw ValidationException::withMessages([
                'password' => 'No HP atau kata sandi salah. Periksa lagi, lalu coba masuk.',
            ]);
        }

        RateLimiter::clear($key);

        return $user;
    }
}
