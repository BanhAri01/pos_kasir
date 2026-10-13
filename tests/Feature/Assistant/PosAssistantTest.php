<?php

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Assistant\Jobs\AnswerAssistantWhatsApp;
use App\Modules\Assistant\Models\AssistantMessage;
use App\Modules\Assistant\Models\TenantAssistantPhone;
use App\Modules\Assistant\Services\AiAgent;
use App\Modules\Assistant\Services\AiTools;
use App\Modules\Assistant\Services\DemoLlm;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customer\Models\Customer;
use App\Modules\Expense\Models\Expense;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Operations\Models\Receivable;
use App\Modules\Pos\Models\Sale;
use App\Modules\Pos\Models\SaleItem;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->owner = registerTenant('toko_kelontong');
    $this->tenant = $this->owner->tenant;
    $this->tools = app(AiTools::class);
    $this->demoLlm = app(DemoLlm::class);
    $this->agent = app(AiAgent::class);
});

describe('uji tools analisis ai pos kasir (hermes pos ai)', function () {
    it('dapat menghitung omset penjualan dengan data riil', function () {
        asTenant($this->tenant, function () {
            $outlet = outletsOf($this->owner)->first();

            Sale::create([
                'outlet_id' => $outlet->id,
                'uuid' => (string) Str::uuid(),
                'number' => 'S-001',
                'cashier_id' => $this->owner->id,
                'status' => 'completed',
                'subtotal' => 150000,
                'total' => 150000,
                'completed_at' => now(),
            ]);

            Sale::create([
                'outlet_id' => $outlet->id,
                'uuid' => (string) Str::uuid(),
                'number' => 'S-002',
                'cashier_id' => $this->owner->id,
                'status' => 'completed',
                'subtotal' => 50000,
                'total' => 50000,
                'completed_at' => now(),
            ]);
        });

        $res = $this->tools->execute('omset_penjualan', ['period' => 'today'], $this->tenant);

        expect($res['total_omset'])->toBe(200000)
            ->and($res['total_transaksi'])->toBe(2)
            ->and($res['rata_rata_per_transaksi'])->toBe(100000);
    });

    it('dapat menampilkan produk terlaris', function () {
        asTenant($this->tenant, function () {
            $outlet = outletsOf($this->owner)->first();
            $p1 = Product::create(['uuid' => (string) Str::uuid(), 'name' => 'Beras Pandan 5kg', 'price' => 75000]);
            $p2 = Product::create(['uuid' => (string) Str::uuid(), 'name' => 'Minyak Goreng 2L', 'price' => 35000]);

            $sale = Sale::create([
                'outlet_id' => $outlet->id,
                'uuid' => (string) Str::uuid(),
                'number' => 'S-101',
                'cashier_id' => $this->owner->id,
                'status' => 'completed',
                'total' => 185000,
                'completed_at' => now(),
            ]);

            SaleItem::create([
                'sale_id' => $sale->id,
                'product_id' => $p1->id,
                'name' => $p1->name,
                'qty' => 2,
                'unit_price' => 75000,
                'original_price' => 75000,
                'subtotal' => 150000,
            ]);

            SaleItem::create([
                'sale_id' => $sale->id,
                'product_id' => $p2->id,
                'name' => $p2->name,
                'qty' => 1,
                'unit_price' => 35000,
                'original_price' => 35000,
                'subtotal' => 35000,
            ]);
        });

        $res = $this->tools->execute('produk_terlaris', ['period' => 'today', 'limit' => 5], $this->tenant);

        expect($res['produk'])->toHaveCount(2)
            ->and($res['produk'][0]['nama_produk'])->toBe('Beras Pandan 5kg')
            ->and($res['produk'][0]['terjual_qty'])->toBe(2.0);
    });

    it('dapat mendeteksi stok yang kritis atau menipis', function () {
        asTenant($this->tenant, function () {
            $outlet = outletsOf($this->owner)->first();
            $product = Product::create([
                'uuid' => (string) Str::uuid(),
                'name' => 'Gula Pasir 1kg',
                'price' => 17000,
                'track_stock' => true,
                'min_stock' => 10,
            ]);

            Stock::create([
                'outlet_id' => $outlet->id,
                'product_id' => $product->id,
                'qty' => 3, // di bawah min_stock 10
            ]);
        });

        $res = $this->tools->execute('stok_menipis', ['limit' => 5], $this->tenant);

        expect($res['stok_kritis'])->toHaveCount(1)
            ->and($res['stok_kritis'][0]['nama'])->toBe('Gula Pasir 1kg')
            ->and($res['stok_kritis'][0]['sisa_stok'])->toBe(3.0)
            ->and($res['stok_kritis'][0]['status'])->toBe('MENIPIS');
    });

    it('dapat menghitung estimasi laba rugi', function () {
        asTenant($this->tenant, function () {
            $outlet = outletsOf($this->owner)->first();

            $sale = Sale::create([
                'outlet_id' => $outlet->id,
                'uuid' => (string) Str::uuid(),
                'number' => 'S-PROFIT',
                'cashier_id' => $this->owner->id,
                'status' => 'completed',
                'total' => 100000,
                'completed_at' => now(),
            ]);

            SaleItem::create([
                'sale_id' => $sale->id,
                'name' => 'Produk A',
                'qty' => 1,
                'unit_price' => 100000,
                'original_price' => 100000,
                'subtotal' => 100000,
                'cost_amount' => 60000, // HPP Rp 60.000 -> Laba kotor Rp 40.000
            ]);

            Expense::create([
                'outlet_id' => $outlet->id,
                'uuid' => (string) Str::uuid(),
                'spent_on' => now()->toDateString(),
                'amount' => 15000, // Biaya listrik Rp 15.000 -> Laba bersih Rp 25.000
                'note' => 'Listrik',
            ]);
        });

        $res = $this->tools->execute('laba_rugi', ['period' => 'today'], $this->tenant);

        expect($res['total_omset'])->toBe(100000)
            ->and($res['total_hpp_modal'])->toBe(60000)
            ->and($res['laba_kotor'])->toBe(40000)
            ->and($res['beban_pengeluaran'])->toBe(15000)
            ->and($res['laba_bersih'])->toBe(25000);
    });

    it('dapat menampilkan daftar kasbon pelanggan', function () {
        asTenant($this->tenant, function () {
            $c = Customer::create([
                'uuid' => (string) Str::uuid(),
                'name' => 'Pak Haji Syahrul',
                'phone' => '081234567899',
            ]);

            Receivable::create([
                'customer_id' => $c->id,
                'amount' => 500000,
                'paid_amount' => 100000,
                'status' => 'open',
                'due_date' => now()->addDays(3)->toDateString(),
            ]);
        });

        $res = $this->tools->execute('kasbon_pelanggan', ['limit' => 5], $this->tenant);

        expect($res['total_seluruh_piutang'])->toBe(400000)
            ->and($res['pelanggan'])->toHaveCount(1)
            ->and($res['pelanggan'][0]['nama'])->toBe('Pak Haji Syahrul')
            ->and($res['pelanggan'][0]['sisa_utang'])->toBe(400000);
    });
});

describe('uji ai agent & antarmuka web asisten toko', function () {
    it('demollm menjawab pertanyaan omset dengan sopan dan natural', function () {
        $result = $this->demoLlm->generate('Berapa omset toko hari ini?', $this->tenant);

        expect($result['text'])->toContain('Laporan Omset')
            ->and($result['tools_called'])->toHaveCount(1)
            ->and($result['tools_called'][0]['name'])->toBe('omset_penjualan');
    });

    it('aiagent menyimpan riwayat pertanyaan dan respon di database', function () {
        $msg = $this->agent->ask(
            prompt: 'Ada barang yang mau habis?',
            tenant: $this->tenant,
            user: $this->owner,
            channel: 'web'
        );

        expect($msg)->toBeInstanceOf(AssistantMessage::class)
            ->and($msg->sender_type)->toBe('assistant')
            ->and(AssistantMessage::where('tenant_id', $this->tenant->id)->count())->toBe(2); // 1 user + 1 assistant
    });

    it('dapat mengirim pertanyaan lewat route web asisten', function () {
        $this->actingAs($this->owner)->post(route('assistant.ask'), [
            'message' => 'Berapa omset hari ini?',
            'driver' => 'demo',
        ])->assertSessionHasNoErrors();

        expect(AssistantMessage::where('tenant_id', $this->tenant->id)->count())->toBe(2);
    });

    it('dapat menambah dan menghapus nomor whatsapp yang diizinkan', function () {
        $this->actingAs($this->owner)->post(route('assistant.phone.store'), [
            'phone' => '081298765432',
            'label' => 'Owner Utama',
        ])->assertSessionHasNoErrors();

        $phone = TenantAssistantPhone::where('tenant_id', $this->tenant->id)->first();
        expect($phone)->not->toBeNull()
            ->and($phone->phone_number)->toBe('6281298765432')
            ->and($phone->label)->toBe('Owner Utama');

        // Hapus
        $this->actingAs($this->owner)->delete(route('assistant.phone.destroy', $phone->id))
            ->assertSessionHasNoErrors();

        expect(TenantAssistantPhone::where('tenant_id', $this->tenant->id)->count())->toBe(0);
    });

    it('memproses webhook fonnte whatsapp untuk nomor yang terdaftar', function () {
        Queue::fake();

        TenantAssistantPhone::create([
            'tenant_id' => $this->tenant->id,
            'phone_number' => '6281277778888',
            'label' => 'Pak Bos',
            'is_active' => true,
        ]);

        $response = $this->postJson(route('assistant.webhook'), [
            'sender' => '081277778888',
            'message' => 'Berapa omset hari ini?',
        ]);

        $response->assertOk()
            ->assertJson(['status' => true]);

        Queue::assertPushed(AnswerAssistantWhatsApp::class, function ($job) {
            return $job->senderPhone === '6281277778888'
                && $job->question === 'Berapa omset hari ini?';
        });
    });

    it('menolak webhook whatsapp dari nomor asing yang belum terdaftar', function () {
        Queue::fake();

        $response = $this->postJson(route('assistant.webhook'), [
            'sender' => '089999999999',
            'message' => 'Berapa omset toko?',
        ]);

        $response->assertStatus(403);
        Queue::assertNothingPushed();
    });

    it('dapat menjalankan command pos:asisten dari artisan cli', function () {
        $this->artisan('pos:asisten', [
            'pertanyaan' => 'Berapa omset hari ini?',
            '--tenant' => $this->tenant->id,
            '--driver' => 'demo',
        ])->assertSuccessful();
    });
});
