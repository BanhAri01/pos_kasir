<?php

require_once __DIR__.'/../Pos/PosTestHelpers.php';
require_once __DIR__.'/../Modules/ModuleTestHelpers.php';

use App\Models\Tenant;
use App\Modules\Customer\Models\Customer;
use App\Modules\Operations\Models\Receivable;
use App\Modules\Operations\Services\KasbonBillService;
use App\Modules\Warehouse\Models\CustomerDeposit;
use App\Modules\WhatsApp\Models\WhatsappAccount;
use App\Modules\WhatsApp\Models\WhatsappMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

function wibTest(string $time): Carbon
{
    return Carbon::parse($time, 'Asia/Jakarta');
}

beforeEach(function () {
    // Buat tenant dengan paket bisnis agar mendukung nomor WhatsApp sendiri (Fonnte)
    $this->owner = registerTenant('toko_kelontong');
    $this->tenant = $this->owner->tenant;
    $this->tenant->update(['plan' => 'bisnis']);

    // Konfigurasi akun WhatsApp usaha menggunakan Fonnte
    asTenant($this->tenant, function () {
        WhatsappAccount::create([
            'tenant_id' => $this->tenant->id,
            'provider' => 'fonnte',
            'sender_phone' => '081299998888',
            'credentials' => ['token' => 'fonnte-live-test-token-xyz'],
            'is_active' => true,
        ]);
    });

    // Aktifkan fitur tagih otomatis dan info rekening pembayaran
    app(KasbonBillService::class)->saveSettings($this->tenant, auto: true, paymentInfo: 'BCA 1234567890 a.n. Toko Kelontong');

    // Buat pelanggan dengan nomor HP yang valid
    $this->customer = makeCustomer($this->owner, ['name' => 'Pak Budi Hartono', 'phone' => '081234567890']);
});

afterEach(function () {
    Carbon::setTestNow();
});

describe('uji sistem pesan otomatis fonnte jatuh tempo kasbon', function () {
    it('mengirim pesan WhatsApp via Fonnte API saat utang jatuh tempo tiba', function () {
        // 1. Catat kasbon/piutang yang jatuh tempo pada 2026-10-13
        asTenant($this->tenant, function () {
            Receivable::create([
                'customer_id' => $this->customer->id,
                'amount' => 150000,
                'paid_amount' => 0,
                'status' => 'open',
                'due_date' => '2026-10-13',
            ]);
        });

        // 2. Set waktu ke jam kerja operasional (09:00 WIB di tanggal jatuh tempo)
        Carbon::setTestNow(wibTest('2026-10-13 09:00:00'));

        // 3. Mock respon sukses dari Fonnte API
        Http::fake([
            'https://api.fonnte.com/send' => Http::response([
                'status' => true,
                'id' => ['fonnte-msg-id-12345'],
                'target' => ['6281234567890'],
            ], 200),
        ]);

        // 4. Jalankan perintah cron pengingat otomatis
        $this->artisan('hermes:send-reminders')->assertSuccessful();

        // 5. Pastikan HTTP request benar-benar terkirim ke Fonnte dengan format yang tepat
        Http::assertSent(function ($request) {
            $isUrl = $request->url() === 'https://api.fonnte.com/send';
            $hasAuthHeader = $request->header('Authorization')[0] === 'fonnte-live-test-token-xyz';
            $isTarget = $request['target'] === '6281234567890';
            $isCountryCode = $request['countryCode'] === '62';
            $message = $request['message'];

            $containsGreeting = str_contains($message, 'Yth. Bapak/Ibu Pak Budi Hartono');
            $containsTagihan = str_contains($message, 'Rp150.000');
            $containsLink = str_contains($message, '/tagihan/');

            return $isUrl && $hasAuthHeader && $isTarget && $isCountryCode && $containsGreeting && $containsTagihan && $containsLink;
        });

        // 6. Pastikan riwayat pesan tersimpan di database dengan status sent & provider fonnte
        $msg = asTenant($this->tenant, fn () => WhatsappMessage::query()->where('template_code', 'kasbon_reminder')->first());
        expect($msg)->not->toBeNull()
            ->and($msg->to_phone)->toBe('6281234567890')
            ->and($msg->provider)->toBe('fonnte')
            ->and($msg->provider_message_id)->toBe('fonnte-msg-id-12345')
            ->and($msg->status)->toBe('sent');

        // 7. Pastikan tanggal pengingat terakhir pada kasbon diperbarui
        $receivable = asTenant($this->tenant, fn () => Receivable::query()->first());
        expect($receivable->last_reminded_at)->not->toBeNull();
    });

    it('memperhitungkan potongan saldo titip uang / deposit pada pesan tagihan fonnte', function () {
        // Catat utang 200.000 dan saldo deposit 50.000 -> sisa bersih 150.000
        asTenant($this->tenant, function () {
            Receivable::create([
                'customer_id' => $this->customer->id,
                'amount' => 200000,
                'paid_amount' => 0,
                'status' => 'open',
                'due_date' => '2026-10-13',
            ]);

            $this->customer->update(['deposit_balance' => 50000]);
        });

        Carbon::setTestNow(wibTest('2026-10-13 10:00:00'));

        Http::fake([
            'https://api.fonnte.com/send' => Http::response([
                'status' => true,
                'id' => ['fonnte-msg-deposit-888'],
            ], 200),
        ]);

        $this->artisan('hermes:send-reminders')->assertSuccessful();

        // Verifikasi isi pesan memuat rincian potongan titip uang
        Http::assertSent(function ($request) {
            $msg = $request['message'];
            return str_contains($msg, 'setelah dipotong titip uang Rp50.000')
                && str_contains($msg, 'Rp150.000');
        });
    });

    it('tidak mengirim pesan bila jatuh tempo belum tiba (misal: H-1)', function () {
        asTenant($this->tenant, function () {
            Receivable::create([
                'customer_id' => $this->customer->id,
                'amount' => 100000,
                'paid_amount' => 0,
                'status' => 'open',
                'due_date' => '2026-10-14', // Jatuh tempo besok
            ]);
        });

        // Hari ini masih tanggal 13
        Carbon::setTestNow(wibTest('2026-10-13 09:00:00'));

        Http::fake();

        $this->artisan('hermes:send-reminders')->assertSuccessful();

        // Tidak ada panggilan HTTP ke Fonnte
        Http::assertNothingSent();
    });

    it('tidak mengirim pesan bila di luar jam operasional (misal jam 20:00 malam)', function () {
        asTenant($this->tenant, function () {
            Receivable::create([
                'customer_id' => $this->customer->id,
                'amount' => 100000,
                'paid_amount' => 0,
                'status' => 'open',
                'due_date' => '2026-10-13',
            ]);
        });

        // Waktu malam (20:00 WIB, batas adalah 08:00 - 18:00 WIB)
        Carbon::setTestNow(wibTest('2026-10-13 20:00:00'));

        Http::fake();

        $this->artisan('hermes:send-reminders')->assertSuccessful();

        Http::assertNothingSent();
    });

    it('bisa mengirim di luar jam operasional bila memakai opsi --force', function () {
        asTenant($this->tenant, function () {
            Receivable::create([
                'customer_id' => $this->customer->id,
                'amount' => 100000,
                'paid_amount' => 0,
                'status' => 'open',
                'due_date' => '2026-10-13',
            ]);
        });

        // Waktu malam (22:00 WIB)
        Carbon::setTestNow(wibTest('2026-10-13 22:00:00'));

        Http::fake([
            'https://api.fonnte.com/send' => Http::response([
                'status' => true,
                'id' => ['fonnte-msg-force-123'],
            ], 200),
        ]);

        $this->artisan('hermes:send-reminders', ['--force' => true])->assertSuccessful();

        Http::assertSent(fn ($req) => $req->url() === 'https://api.fonnte.com/send');
    });

    it('tidak mengirim pesan bila opsi tagih otomatis dinonaktifkan', function () {
        // Matikan tagih otomatis
        app(KasbonBillService::class)->saveSettings($this->tenant, auto: false, paymentInfo: null);

        asTenant($this->tenant, function () {
            Receivable::create([
                'customer_id' => $this->customer->id,
                'amount' => 100000,
                'paid_amount' => 0,
                'status' => 'open',
                'due_date' => '2026-10-13',
            ]);
        });

        Carbon::setTestNow(wibTest('2026-10-13 11:00:00'));

        Http::fake();

        $this->artisan('hermes:send-reminders')->assertSuccessful();

        Http::assertNothingSent();
    });

    it('menangani kegagalan Fonnte dengan benar saat token invalid atau perangkat terputus', function () {
        asTenant($this->tenant, function () {
            Receivable::create([
                'customer_id' => $this->customer->id,
                'amount' => 100000,
                'paid_amount' => 0,
                'status' => 'open',
                'due_date' => '2026-10-13',
            ]);
        });

        Carbon::setTestNow(wibTest('2026-10-13 09:00:00'));

        // Respon error dari Fonnte
        Http::fake([
            'https://api.fonnte.com/send' => Http::response([
                'status' => false,
                'reason' => 'device disconnected',
            ], 200),
        ]);

        try {
            $this->artisan('hermes:send-reminders');
        } catch (\Throwable $e) {
            // SendWhatsAppMessage melempar exception saat coba ulang habis
        }

        // Status di database mencatat failed dan keterangan error
        $msg = asTenant($this->tenant, fn () => WhatsappMessage::query()->first());
        expect($msg)->not->toBeNull()
            ->and($msg->status)->toBe('failed')
            ->and($msg->error)->toContain('device disconnected');
    });
});
