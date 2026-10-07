<?php

namespace App\Modules\Pos\Support;

use App\Modules\Pos\Models\Sale;
use Illuminate\Validation\Rule;

/**
 * Aturan validasi data penjualan, dipakai bersama oleh kasir online (StoreSaleRequest)
 * dan sinkronisasi offline (SyncService) supaya keduanya selalu sama.
 */
class SaleRules
{
    public static function rules(int $tenantId, bool $canDiscount = true): array
    {
        $discount = $canDiscount ? 'nullable' : 'prohibited';

        return [
            'uuid' => ['required', 'uuid'],
            'number' => ['nullable', 'string', 'max:40'],
            'shift_uuid' => ['required', 'uuid'],
            'customer_uuid' => ['nullable', 'uuid'],
            'order_type' => ['nullable', Rule::in(array_keys(Sale::ORDER_TYPES))],
            'note' => ['nullable', 'string', 'max:255'],
            'created_at' => ['nullable', 'date'],
            'table_id' => ['nullable', 'integer', Rule::exists('dining_tables', 'id')->where('tenant_id', $tenantId)],
            'queue_number' => ['nullable', 'string', 'max:10'],
            'pay_later' => ['nullable', 'boolean'],
            'due_date' => ['nullable', 'date'],
            'send_to_kitchen' => ['nullable', 'boolean'],
            'send_whatsapp' => ['nullable', 'boolean'],

            'items' => ['required', 'array', 'min:1', 'max:300'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('tenant_id', $tenantId)],
            'items.*.qty' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'items.*.unit_price' => ['nullable', 'integer', 'min:0'],
            'items.*.unit_id' => ['nullable', 'integer', Rule::exists('product_units', 'id')->where('tenant_id', $tenantId)],
            'items.*.modifiers' => ['nullable', 'array', 'max:20'],
            'items.*.modifiers.*' => ['integer', Rule::exists('modifier_options', 'id')->where('tenant_id', $tenantId)],
            'items.*.staff_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'items.*.discount_amount' => [$discount, 'integer', 'min:0'],
            'items.*.note' => ['nullable', 'string', 'max:150'],

            'discount_type' => [$discount, Rule::in(['percent', 'amount'])],
            'discount_value' => [$discount, 'integer', 'min:0'],

            'payments' => ['nullable', 'array', 'max:5'],
            'payments.*.uuid' => ['nullable', 'uuid'],
            'payments.*.payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')->where('tenant_id', $tenantId)],
            'payments.*.amount' => ['required', 'integer', 'min:0'],
            'payments.*.reference' => ['nullable', 'string', 'max:100'],
        ];
    }
}
