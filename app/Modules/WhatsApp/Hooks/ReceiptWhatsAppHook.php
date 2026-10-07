<?php

namespace App\Modules\WhatsApp\Hooks;

use App\Core\Support\Rupiah;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Pos\Contracts\SaleHook;
use App\Modules\Pos\Models\Sale;
use App\Modules\WhatsApp\Services\WhatsAppService;

/** Struk digital otomatis terkirim ke WhatsApp pelanggan (kalau kasir memilih "kirim WhatsApp"). */
class ReceiptWhatsAppHook implements SaleHook
{
    public function __construct(
        private TenantContext $context,
        private WhatsAppService $whatsapp,
    ) {}

    public function handle(Sale $sale, array $data, User $cashier, bool $strict): void
    {
        if (! $this->context->get()->hasModule('whatsapp_notification') || empty($data['send_whatsapp'])) {
            return;
        }

        $customer = $sale->customer;
        if (! $customer?->phone) {
            return;
        }

        $this->whatsapp->sendTemplate('receipt', $customer->phone, [
            'nama' => $customer->name,
            'nota' => $sale->number,
            'total' => Rupiah::format($sale->total),
            'link' => $sale->receiptUrl(),
        ], $sale);
    }
}
