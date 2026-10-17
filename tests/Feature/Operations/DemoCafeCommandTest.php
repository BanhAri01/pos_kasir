<?php

use App\Models\Tenant;
use App\Models\User;
use App\Modules\WhatsApp\Services\WhatsAppService;
use Illuminate\Support\Facades\DB;

function demoTenant(): Tenant
{
    return Tenant::query()->findOrFail(User::query()->where('phone', '6281100000001')->value('tenant_id'));
}

it('membuat akun demo kedai kopi lengkap yang langsung bisa dipakai', function () {
    $this->artisan('hermes:demo-kafe', ['--days' => 3])->assertSuccessful();

    $tenant = demoTenant();
    $id = $tenant->id;

    expect($tenant->businessType->code)->toBe('coffee_shop')
        ->and($tenant->status)->toBe('active')
        ->and($tenant->plan)->toBe('pro')
        ->and($tenant->hasUnlimitedAccess())->toBeTrue()
        ->and($tenant->hasModule('qr_order'))->toBeTrue()
        ->and($tenant->hasModule('kitchen_display'))->toBeTrue()
        ->and($tenant->settings['wa_off'])->toEqualCanonicalizing(array_keys(WhatsAppService::LABELS))
        ->and($tenant->settings['owner_report']['daily'])->toBeFalse();

    expect(DB::table('products')->where('tenant_id', $id)->where('type', 'goods')->count())->toBe(13)
        ->and(DB::table('products')->where('tenant_id', $id)->where('type', 'ingredient')->count())->toBe(8)
        ->and(DB::table('recipe_items')->where('tenant_id', $id)->count())->toBeGreaterThan(20)
        ->and(DB::table('modifier_groups')->where('tenant_id', $id)->count())->toBe(4)
        ->and(DB::table('dining_tables')->where('tenant_id', $id)->count())->toBe(8)
        ->and(DB::table('users')->where('tenant_id', $id)->count())->toBe(3)
        ->and(DB::table('customers')->where('tenant_id', $id)->count())->toBe(8);

    $sales = DB::table('sales')->where('tenant_id', $id);
    expect((clone $sales)->count())->toBeGreaterThanOrEqual(54)
        ->and((clone $sales)->where('payment_status', '!=', 'paid')->count())->toBe(0)
        ->and((clone $sales)->where('completed_at', '>=', now()->startOfDay())->count())->toBe(0)
        ->and(DB::table('receivables')->where('tenant_id', $id)->count())->toBe(0)
        ->and(DB::table('kitchen_tickets')->where('tenant_id', $id)->count())->toBe(0)
        ->and(DB::table('whatsapp_messages')->where('tenant_id', $id)->count())->toBe(0)
        ->and(DB::table('shifts')->where('tenant_id', $id)->where('status', '!=', 'closed')->count())->toBe(0)
        ->and(DB::table('sale_payments')->where('tenant_id', $id)->sum('amount'))->toEqual((clone $sales)->sum('total'))
        ->and(DB::table('sale_items')->where('tenant_id', $id)->sum('cost_amount'))->toBeGreaterThan(0);

    $coffee = DB::table('products')->where('tenant_id', $id)->where('name', 'Biji Kopi Arabika')->value('id');
    expect((float) DB::table('stocks')->where('product_id', $coffee)->value('qty'))->toBeLessThan(3000.0);
});

it('tidak menyentuh data usaha lain dan menolak dibuat dua kali', function () {
    $other = registerTenant(type: 'coffee_shop', name: 'Kafe Asli');
    $before = DB::table('products')->where('tenant_id', $other->tenant_id)->count();

    $this->artisan('hermes:demo-kafe', ['--days' => 1])->assertSuccessful();
    $this->artisan('hermes:demo-kafe', ['--days' => 1])->assertFailed();

    expect(DB::table('products')->where('tenant_id', $other->tenant_id)->count())->toBe($before)
        ->and(DB::table('sales')->where('tenant_id', $other->tenant_id)->count())->toBe(0)
        ->and(Tenant::query()->where('name', 'Kopi Senja (Demo)')->count())->toBe(1);
});

it('memakai modal bahan yang wajar di data contoh kedai kopi', function () {
    $owner = registerTenant(type: 'coffee_shop', name: 'Kafe Baru');

    expect(DB::table('products')->where('tenant_id', $owner->tenant_id)->where('name', 'Susu Segar')->value('cost_price'))->toBe(20000)
        ->and(DB::table('products')->where('tenant_id', $owner->tenant_id)->where('name', 'Gula Aren Cair')->value('cost_price'))->toBe(60000);
});
