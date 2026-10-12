<?php

namespace Database\Seeders;

use App\Core\Tenancy\TenantContext;
use App\Models\Tenant;
use App\Modules\Customer\Models\Customer;
use App\Modules\Operations\Models\Receivable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class WarehouseDepositDemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::whereIn('id', [2, 3])->get();

        foreach ($tenants as $tenant) {
            app(TenantContext::class)->runAs($tenant, function () use ($tenant) {
                // Hapus demo lama jika ada
                Customer::whereIn('phone', ['081298765432', '085712345678', '087811223344'])->delete();

                // 1. Pak Hendra (Sisa Bersih 310.000)
                $hendra = Customer::create([
                    'uuid' => (string) Str::uuid(),
                    'name' => 'Pak Hendra (Peternakan Ayam Broiler)',
                    'phone' => '081298765432',
                    'deposit_balance' => 0,
                    'bill_token' => Str::random(40),
                ]);
                $hendra->addDeposit(200_000, 'overpayment', 'Kelebihan transfer pembayaran nota sebelumnya', 'warehouse');
                Receivable::create([
                    'customer_id' => $hendra->id,
                    'type' => 'kasbon',
                    'amount' => 510_000,
                    'paid_amount' => 0,
                    'status' => 'open',
                    'due_date' => now()->toDateString(),
                    'note' => 'Nota #W-0891: Pakan Ayam Broiler Starter 10 Karung',
                ]);

                // 2. Ibu Siti (Surplus Deposit 500.000)
                $siti = Customer::create([
                    'uuid' => (string) Str::uuid(),
                    'name' => 'Ibu Siti (Peternakan Bebek Barokah)',
                    'phone' => '085712345678',
                    'deposit_balance' => 0,
                    'bill_token' => Str::random(40),
                ]);
                $siti->addDeposit(500_000, 'deposit', 'Titip uang tunai untuk belanja konsentrat pakan bebek', 'warehouse');

                // 3. Bapak Darmawan (Nota 350.000, Deposit 100.000, Sisa Bersih 250.000)
                $darmawan = Customer::create([
                    'uuid' => (string) Str::uuid(),
                    'name' => 'Bapak Darmawan (Kandang Puyuh Sukses)',
                    'phone' => '087811223344',
                    'deposit_balance' => 0,
                    'bill_token' => Str::random(40),
                ]);
                $darmawan->addDeposit(100_000, 'deposit', 'Uang muka / titip uang awal', 'warehouse');
                Receivable::create([
                    'customer_id' => $darmawan->id,
                    'type' => 'kasbon',
                    'amount' => 350_000,
                    'paid_amount' => 0,
                    'status' => 'open',
                    'due_date' => now()->addDays(3)->toDateString(),
                    'note' => 'Nota #W-0895: Obat & Vitamin Unggas Komplit',
                ]);

                // Demo Siklus Kandang
                \App\Modules\Warehouse\Models\FarmingCycle::whereIn('customer_id', [$hendra->id, $darmawan->id])->delete();

                \App\Modules\Warehouse\Models\FarmingCycle::create([
                    'tenant_id' => $tenant->id,
                    'customer_id' => $hendra->id,
                    'cycle_code' => 'SKL-HEN-2610-01',
                    'kandang_name' => 'Kandang Broiler Barat 1',
                    'livestock_type' => 'broiler',
                    'population' => 2500,
                    'start_date' => now()->subDays(28)->toDateString(),
                    'harvest_estimate_date' => now()->addDays(7)->toDateString(),
                    'target_fcr' => 1.55,
                    'total_feed_bags' => 110,
                    'total_feed_weight' => 5500,
                    'total_feed_amount' => 52800000,
                    'deposit_deducted' => 200000,
                    'net_remaining_due' => 52600000,
                    'status' => 'active',
                    'notes' => 'Bibit DOC Platinum, target bobot panen 2.05 kg',
                ]);

                \App\Modules\Warehouse\Models\FarmingCycle::create([
                    'tenant_id' => $tenant->id,
                    'customer_id' => $darmawan->id,
                    'cycle_code' => 'SKL-DAR-2610-02',
                    'kandang_name' => 'Kandang Puyuh Sukses Blok A',
                    'livestock_type' => 'puyuh',
                    'population' => 5000,
                    'start_date' => now()->subDays(40)->toDateString(),
                    'harvest_estimate_date' => now()->subDays(2)->toDateString(),
                    'actual_harvest_date' => now()->subDays(2)->toDateString(),
                    'target_fcr' => 2.20,
                    'total_feed_bags' => 80,
                    'total_feed_weight' => 4000,
                    'total_feed_amount' => 38400000,
                    'deposit_deducted' => 100000,
                    'net_remaining_due' => 38300000,
                    'status' => 'harvested',
                    'notes' => 'Siap pelunasan akhir panen telur puyuh',
                ]);
            });
        }
    }
}
