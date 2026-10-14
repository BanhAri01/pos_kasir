<?php

require_once __DIR__.'/../Pos/PosTestHelpers.php';
require_once __DIR__.'/../Modules/ModuleTestHelpers.php';

use App\Modules\Operations\Models\Receivable;
use App\Modules\Operations\Models\ReceivableProof;
use App\Modules\Operations\Services\KasbonBillService;
use App\Modules\WhatsApp\Models\WhatsappMessage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function kasbonMessages($owner, string $code)
{
    return asTenant($owner->tenant, fn () => WhatsappMessage::query()->where('template_code', $code)->get());
}

function wib(string $time): Carbon
{
    return Carbon::parse($time, 'Asia/Jakarta');
}

function proofImage(): UploadedFile
{
    return UploadedFile::fake()->image('bukti.jpg', 1200, 1600);
}

beforeEach(function () {
    Storage::fake(KasbonBillService::DISK);
    $this->owner = registerTenant('toko_kelontong');
    $this->cash = paymentMethod($this->owner);
    $this->beras = productNamed($this->owner, 'Beras Premium 5 kg');
    $this->shift = openShift($this, $this->owner);
    $this->joko = makeCustomer($this->owner);
});

afterEach(fn () => Carbon::setTestNow());

describe('tanggal tagih bulanan', function () {
    it('menghitung tanggal tagih berikutnya, selalu setelah hari ini', function (string $today, int $day, string $expected) {
        expect(KasbonBillService::nextBillingDate($day, Carbon::parse($today))->toDateString())->toBe($expected);
    })->with([
        'masih di bulan ini' => ['2026-10-09', 25, '2026-10-25'],
        'hari ini = tanggal tagih, ke bulan depan' => ['2026-10-25', 25, '2026-11-25'],
        'sudah lewat, ke bulan depan' => ['2026-10-26', 1, '2026-11-01'],
        'akhir tahun' => ['2026-12-30', 5, '2027-01-05'],
        'bulan pendek' => ['2027-01-31', 28, '2027-02-28'],
    ]);

    it('kasbon dari kasir tanpa tanggal otomatis jatuh tempo di tanggal tagih pelanggan', function () {
        Carbon::setTestNow(wib('2026-10-09 10:00:00'));
        $this->actingAs($this->owner)->put(route('receivables.billing-day', $this->joko), ['billing_day' => 1])->assertSessionHas('success');

        sell($this, $this->owner, $this->shift, [['product_id' => $this->beras->id, 'qty' => 1]], [], ['pay_later' => true, 'customer_uuid' => $this->joko->uuid])->assertCreated();

        $receivable = asTenant($this->owner->tenant, fn () => Receivable::query()->where('customer_id', $this->joko->id)->first());
        expect($receivable->due_date->toDateString())->toBe('2026-11-01');
    });

    it('tanggal di nota tetap dipakai, dan kasbon lama tanpa tanggal ikut diisi saat jadwal disimpan', function () {
        Carbon::setTestNow(wib('2026-10-09 10:00:00'));
        $this->actingAs($this->owner)->post(route('receivables.store'), ['customer_id' => $this->joko->id, 'amount' => 50000]);
        $this->actingAs($this->owner)->post(route('receivables.store'), ['customer_id' => $this->joko->id, 'amount' => 20000, 'due_date' => '2026-10-15']);

        $this->actingAs($this->owner)->put(route('receivables.billing-day', $this->joko), ['billing_day' => 20])->assertSessionHas('success');

        $dates = asTenant($this->owner->tenant, fn () => Receivable::query()->orderBy('amount')->pluck('due_date')->map->toDateString()->all());
        expect($dates)->toBe(['2026-10-15', '2026-10-20']);

        $this->actingAs($this->owner)->put(route('receivables.billing-day', $this->joko), ['billing_day' => 31])->assertSessionHasErrors('billing_day');
    });
});

describe('tagih otomatis', function () {
    beforeEach(function () {
        Carbon::setTestNow(wib('2026-10-09 10:00:00'));
        $this->actingAs($this->owner)->post(route('receivables.store'), ['customer_id' => $this->joko->id, 'amount' => 75000, 'due_date' => '2026-10-10']);
    });

    it('tidak mengirim apa pun selama tagih otomatis mati', function () {
        Carbon::setTestNow(wib('2026-10-10 09:00:00'));
        $this->artisan('hermes:send-reminders')->assertSuccessful();

        expect(kasbonMessages($this->owner, 'kasbon_reminder'))->toHaveCount(0);
    });

    it('mengirim tagihan resmi saat jatuh tempo, sekali, lalu mengulang setelah 7 hari', function () {
        $this->actingAs($this->owner)->put(route('receivables.settings'), ['auto' => true, 'payment_info' => 'BCA 123 a.n. Gudang'])->assertSessionHas('success');

        Carbon::setTestNow(wib('2026-10-09 23:00:00'));
        $this->artisan('hermes:send-reminders');
        expect(kasbonMessages($this->owner, 'kasbon_reminder'))->toHaveCount(0);

        Carbon::setTestNow(wib('2026-10-10 06:00:00'));
        $this->artisan('hermes:send-reminders');
        expect(kasbonMessages($this->owner, 'kasbon_reminder'))->toHaveCount(0);

        Carbon::setTestNow(wib('2026-10-10 08:30:00'));
        $this->artisan('hermes:send-reminders');
        $this->artisan('hermes:send-reminders');
        $messages = kasbonMessages($this->owner, 'kasbon_reminder');
        expect($messages)->toHaveCount(1)
            ->and($messages[0]->body)->toContain('Yth. Bapak/Ibu Pak Joko')->toContain('Rp75.000')->toContain('/tagihan/');

        Carbon::setTestNow(wib('2026-10-16 09:00:00'));
        $this->artisan('hermes:send-reminders');
        expect(kasbonMessages($this->owner, 'kasbon_reminder'))->toHaveCount(1);

        Carbon::setTestNow(wib('2026-10-17 09:00:00'));
        $this->artisan('hermes:send-reminders');
        expect(kasbonMessages($this->owner, 'kasbon_reminder'))->toHaveCount(2);
    });

    it('berhenti menagih setelah lunas', function () {
        $this->actingAs($this->owner)->put(route('receivables.settings'), ['auto' => true]);
        $this->actingAs($this->owner)->post(route('receivables.pay', $this->joko), ['amount' => 75000]);

        Carbon::setTestNow(wib('2026-10-10 09:00:00'));
        $this->artisan('hermes:send-reminders');

        expect(kasbonMessages($this->owner, 'kasbon_reminder'))->toHaveCount(0);
    });
});

describe('halaman tagihan & bukti bayar', function () {
    beforeEach(function () {
        sell($this, $this->owner, $this->shift, [['product_id' => $this->beras->id, 'qty' => 1]], [], ['pay_later' => true, 'customer_uuid' => $this->joko->uuid])->assertCreated();
        $this->link = asTenant($this->owner->tenant, fn () => app(KasbonBillService::class)->billUrl($this->joko->fresh()));
        $this->token = basename($this->link);
    });

    it('menampilkan rincian nota tanpa login, dan 404 untuk link asal', function () {
        $this->get($this->link)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Order/Bill')
            ->where('customer', 'Pak Joko')
            ->where('balance', 72000)
            ->has('items', 1)
            ->where('items.0.receipt_url', fn ($url) => str_contains($url, '/struk/') && str_contains($url, 'signature=')));

        $this->get($this->link)->assertSee('noindex, nofollow', false);
        $this->get(route('kasbon.bill', str_repeat('a', 40)))->assertNotFound();
    });

    it('menerima bukti bayar, memberi tahu pemilik, lalu pemilik menerima dan utang berkurang', function () {
        $this->post(route('kasbon.bill.proof', $this->token), ['amount' => 50000, 'file' => proofImage(), 'note' => 'transfer BCA'])
            ->assertSessionHas('success');

        $proof = asTenant($this->owner->tenant, fn () => ReceivableProof::query()->firstOrFail());
        expect($proof->status)->toBe('pending')->and($proof->file_mime)->toBe('image/jpeg');
        Storage::disk(KasbonBillService::DISK)->assertExists($proof->file_path);

        $owner = kasbonMessages($this->owner, 'kasbon_proof');
        expect($owner)->toHaveCount(1)
            ->and($owner[0]->to_phone)->toBe($this->owner->phone)
            ->and($owner[0]->body)->toContain('Pak Joko')->toContain('Rp50.000');

        $this->actingAs($this->owner)->get(route('receivables.proofs.file', $proof))->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        $this->actingAs($this->owner)->post(route('receivables.proofs.approve', $proof), ['amount' => 50000, 'payment_method_id' => $this->cash->id])
            ->assertSessionHas('success', 'Pembayaran diterima. Sisa utang Rp22.000.');
        $this->actingAs($this->owner)->post(route('receivables.proofs.approve', $proof), ['amount' => 50000])->assertSessionHasErrors('proof');

        expect(asTenant($this->owner->tenant, fn () => $this->joko->fresh()->outstandingBalance()))->toBe(22000)
            ->and($proof->fresh()->status)->toBe('approved')
            ->and(kasbonMessages($this->owner, 'kasbon_paid')->first()->body)->toContain('Rp50.000')->toContain('Rp22.000');
    });

    it('menolak bukti dengan alasan dan pelanggan dikabari beserta link kirim ulang', function () {
        $this->post(route('kasbon.bill.proof', $this->token), ['amount' => 72000, 'file' => proofImage()]);
        $proof = asTenant($this->owner->tenant, fn () => ReceivableProof::query()->firstOrFail());

        $this->actingAs($this->owner)->post(route('receivables.proofs.reject', $proof), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($this->owner)->post(route('receivables.proofs.reject', $proof), ['reason' => 'Dana belum masuk ke rekening'])->assertSessionHas('success');

        expect($proof->fresh()->status)->toBe('rejected')
            ->and(asTenant($this->owner->tenant, fn () => $this->joko->fresh()->outstandingBalance()))->toBe(72000)
            ->and(kasbonMessages($this->owner, 'kasbon_rejected')->first()->body)->toContain('Dana belum masuk')->toContain($this->token);
    });

    it('menolak jumlah melebihi tagihan, file bukan gambar/PDF, dan bukti menumpuk', function () {
        $this->post(route('kasbon.bill.proof', $this->token), ['amount' => 999999, 'file' => proofImage()])->assertSessionHasErrors('amount');
        $this->post(route('kasbon.bill.proof', $this->token), ['amount' => 1000, 'file' => UploadedFile::fake()->create('x.php', 10, 'text/plain')])->assertSessionHasErrors('file');
        $this->post(route('kasbon.bill.proof', $this->token), ['amount' => 1000, 'file' => UploadedFile::fake()->create('palsu.jpg', 10, 'image/jpeg')])->assertSessionHasErrors('file');
    });

    it('menahan bukti baru kalau sudah ada 3 yang menunggu dicek', function () {
        foreach (range(1, 3) as $i) {
            $this->post(route('kasbon.bill.proof', $this->token), ['amount' => 1000, 'file' => proofImage()])->assertSessionHasNoErrors();
        }
        $this->post(route('kasbon.bill.proof', $this->token), ['amount' => 1000, 'file' => proofImage()])->assertSessionHasErrors('file');
    });

    it('menyimpan PDF apa adanya', function () {
        $pdf = UploadedFile::fake()->createWithContent('bukti.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");

        $this->post(route('kasbon.bill.proof', $this->token), ['amount' => 10000, 'file' => $pdf])->assertSessionHasNoErrors();

        $proof = asTenant($this->owner->tenant, fn () => ReceivableProof::query()->first());
        expect($proof->file_mime)->toBe('application/pdf');

        $response = $this->actingAs($this->owner)->get(route('receivables.proofs.file', $proof))->assertOk();
        expect($response->headers->get('Content-Disposition'))->toStartWith('attachment')
            ->and($response->headers->get('Content-Security-Policy'))->toContain('sandbox');
    });

    it('link lama mati setelah diganti', function () {
        $this->actingAs($this->owner)->post(route('receivables.reset-link', $this->joko))->assertSessionHas('success');
        auth()->logout();

        $this->get($this->link)->assertNotFound();
    });

    it('usaha lain tidak bisa melihat atau memproses bukti', function () {
        $this->post(route('kasbon.bill.proof', $this->token), ['amount' => 10000, 'file' => proofImage()]);
        $proof = asTenant($this->owner->tenant, fn () => ReceivableProof::query()->firstOrFail());
        $stranger = registerTenant('toko_kelontong', null, 'Toko Lain');

        $this->actingAs($stranger)->get(route('receivables.proofs.file', $proof))->assertNotFound();
        $this->actingAs($stranger)->post(route('receivables.proofs.approve', $proof), ['amount' => 10000])->assertNotFound();
        $this->actingAs($stranger)->post(route('receivables.proofs.reject', $proof), ['reason' => 'x'])->assertNotFound();

        expect($proof->fresh()->status)->toBe('pending');
    });

    it('kasir tidak bisa melihat, menerima, atau mengubah pengaturan tagihan', function () {
        $this->post(route('kasbon.bill.proof', $this->token), ['amount' => 10000, 'file' => proofImage()]);
        $proof = asTenant($this->owner->tenant, fn () => ReceivableProof::query()->firstOrFail());
        $kasir = addStaff($this->owner, 'kasir');

        $this->actingAs($kasir)->post(route('receivables.proofs.approve', $proof), ['amount' => 10000])->assertForbidden();
        $this->actingAs($kasir)->get(route('receivables.proofs.file', $proof))->assertForbidden();
        $this->actingAs($kasir)->put(route('receivables.settings'), ['auto' => true])->assertForbidden();
        $this->actingAs($kasir)->get(route('receivables.show', $this->joko))->assertInertia(fn (Assert $page) => $page->where('proofs', []));
        $this->actingAs($kasir)->get(route('receivables.index'))->assertInertia(fn (Assert $page) => $page->where('pendingProofs', [])->where('canConfigure', false));
    });

    it('halaman kasbon pemilik menampilkan bukti yang menunggu', function () {
        $this->post(route('kasbon.bill.proof', $this->token), ['amount' => 10000, 'file' => proofImage()]);

        $this->actingAs($this->owner)->get(route('receivables.index'))->assertInertia(fn (Assert $page) => $page
            ->has('pendingProofs', 1)->where('pendingProofs.0.customer', 'Pak Joko')->where('billSettings.auto', false));
        $this->actingAs($this->owner)->get(route('receivables.show', $this->joko))->assertInertia(fn (Assert $page) => $page
            ->has('proofs', 1)->where('billing.link', $this->link));
    });
});
