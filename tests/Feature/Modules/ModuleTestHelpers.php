<?php

use App\Core\Modules\ModuleManager;
use App\Modules\Customer\Models\Customer;
use App\Modules\Inventory\Services\StockService;
use Illuminate\Support\Str;

function enableModule($owner, string $code): void
{
    asTenant($owner->tenant, fn () => app(ModuleManager::class)->enable($owner->tenant, $code));
}

function makeCustomer($owner, array $attrs = []): Customer
{
    return asTenant($owner->tenant, fn () => Customer::create(['uuid' => (string) Str::uuid(), 'name' => 'Pak Joko', 'phone' => '6281311112222', ...$attrs]));
}

function sell($test, $user, string $shift, array $items, array $payments, array $extra = [])
{
    return $test->actingAs($user)->postJson(route('pos.api.sales.store'), salePayload($shift, $items, $payments, $extra));
}

function qtyOf($owner, $product): string
{
    return asTenant($owner->tenant, fn () => app(StockService::class)->qty(outletsOf($owner)->first()->id, $product));
}
