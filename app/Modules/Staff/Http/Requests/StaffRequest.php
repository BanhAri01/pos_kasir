<?php

namespace App\Modules\Staff\Http\Requests;

use App\Core\Support\Phone;
use App\Enums\Role;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StaffRequest extends FormRequest
{
    /** Cek izin dulu (StaffPolicy) sebelum validasi, supaya yang tidak berhak langsung ditolak. */
    public function authorize(): bool
    {
        $staff = $this->route('staff');

        return $staff
            ? $this->user()->can('update', $staff)
            : $this->user()->can('create', User::class);
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('phone')) {
            $this->merge(['phone' => Phone::normalize($this->input('phone')) ?? $this->input('phone')]);
        }
    }

    public function rules(): array
    {
        /** @var User|null $staff */
        $staff = $this->route('staff');
        $isSelf = $staff && $staff->id === $this->user()->id;
        $assignable = array_map(fn (Role $r) => $r->value, $this->user()->role()?->canAssign() ?? []);
        $role = $isSelf ? $staff->role()?->value : $this->input('role');

        return [
            'name' => ['required', 'string', 'max:100'],
            // Mengubah data diri sendiri tidak boleh mengganti jabatan.
            'role' => $isSelf ? ['prohibited'] : ['required', Rule::in($assignable)],
            'job_title' => ['nullable', 'string', 'max:50'],
            'outlet_ids' => ['required', 'array', 'min:1'],
            'outlet_ids.*' => ['integer', Rule::in(Outlet::query()->pluck('id')->all())],
            // PIN wajib saat membuat karyawan baru, opsional saat mengubah (kosong = tidak diganti).
            'pin' => [$staff ? 'nullable' : 'required', 'digits_between:4,6'],
            // No HP & kata sandi hanya untuk yang masuk dengan no HP (pemilik/manajer).
            'phone' => [
                Rule::requiredIf($role === Role::Manager->value && ! $staff?->password),
                'nullable', 'regex:/^628\d{7,12}$/',
                Rule::unique('users', 'phone')->ignore($staff?->id),
            ],
            'password' => [
                Rule::requiredIf($role === Role::Manager->value && ! $staff?->password),
                'nullable', 'string', 'min:6', 'max:100',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama karyawan belum diisi.',
            'role.required' => 'Pilih dulu jabatannya.',
            'role.in' => 'Anda tidak bisa memberi jabatan ini.',
            'role.prohibited' => 'Jabatan sendiri tidak bisa diubah.',
            'outlet_ids.required' => 'Pilih minimal satu outlet tempat karyawan bekerja.',
            'outlet_ids.min' => 'Pilih minimal satu outlet tempat karyawan bekerja.',
            'outlet_ids.*.in' => 'Outlet yang dipilih tidak ditemukan.',
            'pin.required' => 'PIN belum diisi. PIN dipakai karyawan untuk masuk.',
            'pin.digits_between' => 'PIN harus berupa 4 sampai 6 angka.',
            'phone.required' => 'Manajer perlu no HP untuk masuk.',
            'phone.regex' => 'No HP sepertinya salah. Contoh yang benar: 0812 3456 7890.',
            'phone.unique' => 'No HP ini sudah dipakai akun lain.',
            'password.required' => 'Manajer perlu kata sandi untuk masuk.',
            'password.min' => 'Kata sandi minimal 6 huruf atau angka.',
        ];
    }
}
