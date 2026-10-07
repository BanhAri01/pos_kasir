<?php

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Pos\Models\PaymentMethod;
use Illuminate\Support\Str;

/** Buka kasir lewat API, kembalikan uuid shift. */
function openShift($test, User $user, int $openingCash = 100000): string
{
    $uuid = (string) Str::uuid();
    $test->actingAs($user)->postJson(route('pos.api.shifts.open'), ['uuid' => $uuid, 'opening_cash' => $openingCash])->assertCreated();

    return $uuid;
}

function paymentMethod(User $user, string $type = 'cash'): PaymentMethod
{
    return asTenant($user->tenant, fn () => PaymentMethod::where('type', $type)->firstOrFail());
}

function productNamed(User $user, string $name): Product
{
    return asTenant($user->tenant, fn () => Product::where('name', $name)->firstOrFail());
}

/** Payload penjualan sederhana. */
function salePayload(string $shiftUuid, array $items, array $payments, array $extra = []): array
{
    return [
        'uuid' => (string) Str::uuid(),
        'number' => 'A-'.now()->format('ymd').'-'.random_int(1000, 9999),
        'shift_uuid' => $shiftUuid,
        'items' => $items,
        'payments' => $payments,
        ...$extra,
    ];
}
