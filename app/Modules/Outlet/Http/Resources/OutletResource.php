<?php

namespace App\Modules\Outlet\Http\Resources;

use App\Core\Support\Phone;
use App\Models\Outlet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Outlet */
class OutletResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $percent = fn (int $bp) => rtrim(rtrim(number_format($bp / 100, 2, ',', ''), '0'), ',');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type ?? 'store',
            'is_warehouse' => $this->isWarehouse(),
            'address' => $this->address,
            'phone' => Phone::display($this->phone),
            'is_active' => $this->is_active,
            'staff_count' => $this->whenCounted('users'),
            'tax_rate' => $percent($this->tax_rate_bp),
            'tax_inclusive' => $this->tax_inclusive,
            'service_charge' => $percent($this->service_charge_bp),
            'receipt_header' => $this->receipt_header,
            'receipt_footer' => $this->receipt_footer,
            'receipt_paper' => $this->receipt_paper ?? '58',
        ];
    }
}
