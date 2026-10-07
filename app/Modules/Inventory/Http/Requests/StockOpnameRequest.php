<?php

namespace App\Modules\Inventory\Http\Requests;

class StockOpnameRequest extends StockItemsRequest
{
    protected string $qtyField = 'counted_qty';

    protected bool $allowZero = true;

    public function rules(): array
    {
        return $this->itemRules();
    }
}
