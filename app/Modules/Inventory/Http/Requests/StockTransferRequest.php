<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Core\Tenancy\TenantContext;
use Illuminate\Validation\Rule;

class StockTransferRequest extends StockItemsRequest
{
    public function rules(): array
    {
        return [
            ...$this->itemRules(),
            'to_outlet_id' => [
                'required', 'integer',
                Rule::exists('outlets', 'id')->where('tenant_id', app(TenantContext::class)->id())->whereNull('deleted_at'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'to_outlet_id.required' => 'Pilih outlet tujuan.',
            'to_outlet_id.exists' => 'Outlet tujuan tidak ditemukan.',
        ];
    }
}
