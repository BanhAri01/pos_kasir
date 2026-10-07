<?php

namespace App\Modules\Pos\Http\Requests;

use App\Core\Tenancy\TenantContext;
use App\Enums\Permission;
use App\Modules\Pos\Support\SaleRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::UsePos->value);
    }

    public function rules(): array
    {
        $rules = SaleRules::rules(app(TenantContext::class)->id(), $this->user()->can(Permission::GiveDiscount->value));

        // Saat online, cara bayar wajib dipilih kecuali "bayar nanti".
        $rules['payments'] = [$this->boolean('pay_later') ? 'nullable' : 'required', 'array', 'max:5'];
        if ($this->input('discount_type') === 'percent') {
            $rules['discount_value'][] = 'max:10000';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Keranjang masih kosong. Pilih barang dulu.',
            'items.*.product_id.exists' => 'Ada barang yang tidak ditemukan. Muat ulang halaman kasir.',
            'items.*.qty.gt' => 'Jumlah barang harus lebih dari 0.',
            'items.*.discount_amount.prohibited' => 'Anda tidak punya izin memberi diskon.',
            'discount_type.prohibited' => 'Anda tidak punya izin memberi diskon.',
            'discount_value.prohibited' => 'Anda tidak punya izin memberi diskon.',
            'discount_value.max' => 'Diskon tidak boleh lebih dari 100%.',
            'payments.required' => 'Pilih cara bayar dulu.',
            'payments.*.payment_method_id.exists' => 'Cara bayar tidak ditemukan.',
        ];
    }
}
