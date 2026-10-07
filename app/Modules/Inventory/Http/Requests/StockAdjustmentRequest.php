<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Models\StockAdjustment;
use Illuminate\Validation\Rule;

class StockAdjustmentRequest extends StockItemsRequest
{
    public function rules(): array
    {
        $type = $this->input('type');

        return [
            ...$this->itemRules(),
            'type' => ['required', Rule::in(['in', 'out'])],
            'reason' => ['required', Rule::in(array_keys(StockAdjustment::REASONS[$type] ?? []))],
            'items.*.unit_cost' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'reason.required' => 'Pilih dulu alasannya.',
            'reason.in' => 'Pilih dulu alasannya.',
        ];
    }
}
