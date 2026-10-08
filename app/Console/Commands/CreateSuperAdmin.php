<?php

namespace App\Console\Commands;

use App\Core\Support\Phone;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateSuperAdmin extends Command
{
    protected $signature = 'hermes:admin {phone : No HP admin} {name=Admin Hermes : Nama admin}';

    protected $description = 'Buat atau perbarui akun admin platform Hermes POS dengan kata sandi acak';

    public function handle(): int
    {
        $phone = Phone::normalize($this->argument('phone'));
        if (! $phone) {
            $this->error('No HP tidak valid.');

            return self::FAILURE;
        }

        $existing = User::withTrashed()->where('phone', $phone)->first();
        if ($existing && $existing->tenant_id) {
            $this->error('No HP ini sudah dipakai akun usaha. Pakai no HP lain untuk admin.');

            return self::FAILURE;
        }

        $password = Str::password(14, symbols: false);
        $user = $existing ?? new User;
        $user->forceFill([
            'name' => $this->argument('name'),
            'phone' => $phone,
            'password' => Hash::make($password),
            'is_super_admin' => true,
            'is_active' => true,
            'deleted_at' => null,
        ])->save();

        $this->info('Akun admin siap. Masuk dengan:');
        $this->line('  No HP      : '.Phone::display($phone));
        $this->line('  Kata sandi : '.$password);
        $this->warn('Simpan kata sandi ini sekarang. Kata sandi tidak ditampilkan lagi.');

        return self::SUCCESS;
    }
}
