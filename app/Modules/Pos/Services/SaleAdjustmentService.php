<?php

namespace App\Modules\Pos\Services;

use App\Core\Support\NumberSequence;
use App\Core\Support\Qty;
use App\Core\Support\Rupiah;
use App\Enums\Permission;
use App\Models\User;
use App\Core\Tenancy\TenantContext;
use App\Modules\Operations\Models\Membership;
use App\Modules\Operations\Models\Receivable;
use App\Modules\Operations\Models\StaffCommission;
use App\Modules\Pos\Models\PaymentMethod;
use App\Modules\Pos\Models\Refund;
use App\Modules\Pos\Models\Sale;
use App\Modules\Pos\Models\SaleItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Void (batalkan seluruh transaksi) dan refund (kembalikan sebagian / semua barang).
 * Keduanya butuh izin atau persetujuan PIN atasan, dan selalu tercatat di activity log.
 */
class SaleAdjustmentService
{
    public function __construct(
        private SaleStockService $saleStock,
        private TenantContext $context,
        private ApprovalService $approval,
        private NumberSequence $numbers,
    ) {}

    public function void(Sale $sale, string $reason, User $actor, ?int $approverId = null, ?string $pin = null): Sale
    {
        $approver = $this->approval->authorize($actor, Permission::VoidSale, $approverId, $pin);

        return DB::transaction(function () use ($sale, $reason, $actor, $approver) {
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);

            if ($sale->isVoid()) {
                throw ValidationException::withMessages(['sale' => 'Transaksi ini sudah dibatalkan sebelumnya.']);
            }

            if ($sale->refunded_amount > 0) {
                throw ValidationException::withMessages([
                    'sale' => 'Transaksi ini sudah pernah dikembalikan sebagian. Pakai "Pengembalian" untuk sisanya.',
                ]);
            }

            $tenant = $this->context->get();
            foreach ($sale->items()->with('product')->get() as $item) {
                if ($item->product) {
                    $this->saleStock->restore($tenant, $sale, $item->product, $item->qty, $item->conversion_qty, 'sale_void', $sale, $actor->id);
                }
            }

            // Batalkan juga hal yang ikut tercatat dari penjualan ini.
            StaffCommission::query()->where('sale_id', $sale->id)->where('status', 'pending')->update(['status' => 'canceled']);
            Receivable::query()->where('sale_id', $sale->id)->where('status', 'open')->update(['status' => 'canceled']);
            Membership::query()->where('sale_id', $sale->id)->update(['status' => 'canceled']);

            $sale->update([
                'status' => 'void',
                'voided_at' => now(),
                'void_reason' => $reason,
                'voided_by' => $actor->id,
                'authorized_by' => $approver?->id,
            ]);

            activity('penjualan')->performedOn($sale)->causedBy($actor)
                ->withProperties(['alasan' => $reason, 'total' => $sale->total, 'disetujui' => $approver?->name])
                ->log("Membatalkan transaksi {$sale->number}");

            return $sale;
        });
    }

    /**
     * @param  list<array{sale_item_id: int, qty: string|float}>  $items
     */
    public function refund(
        Sale $sale,
        array $items,
        string $reason,
        bool $restock,
        ?int $paymentMethodId,
        User $actor,
        ?int $shiftId = null,
        ?int $approverId = null,
        ?string $pin = null,
        ?string $uuid = null,
    ): Refund {
        if ($uuid && ($existing = Refund::query()->where('uuid', $uuid)->first())) {
            return $existing;
        }

        $approver = $this->approval->authorize($actor, Permission::RefundSale, $approverId, $pin);

        return DB::transaction(function () use ($sale, $items, $reason, $restock, $paymentMethodId, $actor, $shiftId, $approver, $uuid) {
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);

            if ($sale->isVoid()) {
                throw ValidationException::withMessages(['sale' => 'Transaksi yang sudah dibatalkan tidak bisa dikembalikan.']);
            }

            $saleItems = $sale->items()->with('product')->get()->keyBy('id');
            $itemsNetTotal = max(1, (int) $saleItems->sum('subtotal'));
            $method = $paymentMethodId ? PaymentMethod::query()->find($paymentMethodId) : PaymentMethod::query()->where('type', 'cash')->first();

            $lines = [];
            $amount = 0;

            foreach ($items as $row) {
                /** @var SaleItem|null $item */
                $item = $saleItems[$row['sale_item_id']] ?? null;
                $qty = Qty::normalize($row['qty']);

                if (! $item) {
                    throw ValidationException::withMessages(['items' => 'Ada barang yang bukan bagian dari transaksi ini.']);
                }

                $remaining = Qty::sub($item->qty, $item->refunded_qty);
                if (Qty::cmp($qty, $remaining) > 0) {
                    throw ValidationException::withMessages([
                        'items' => "Jumlah {$item->name} yang dikembalikan melebihi yang dibeli (sisa ".Qty::display($remaining).').',
                    ]);
                }

                // Uang kembali sebanding dengan bagian barang ini dari total yang dibayar
                // (sudah termasuk diskon transaksi, service charge, dan pajak).
                $share = (float) bcdiv(bcmul((string) $item->subtotal, $qty, 4), Qty::normalize($item->qty), 4);
                $lineAmount = (int) round($sale->total * $share / $itemsNetTotal);

                $lines[] = [$item, $qty, $lineAmount];
                $amount += $lineAmount;
            }

            // Jangan sampai uang kembali melebihi sisa yang pernah dibayar.
            $amount = min($amount, $sale->total - $sale->refunded_amount);

            $refund = Refund::create([
                'sale_id' => $sale->id,
                'uuid' => $uuid ?? (string) Str::uuid(),
                'number' => $this->numbers->next('RF'),
                'amount' => $amount,
                'reason' => $reason,
                'restock' => $restock,
                'payment_method_id' => $method?->id,
                'method_type' => $method?->type ?? 'cash',
                'shift_id' => $shiftId,
                'user_id' => $actor->id,
                'approved_by' => $approver?->id,
            ]);

            foreach ($lines as [$item, $qty, $lineAmount]) {
                $refund->items()->create(['sale_item_id' => $item->id, 'qty' => $qty, 'amount' => $lineAmount]);
                $item->update(['refunded_qty' => Qty::add($item->refunded_qty, $qty)]);

                if ($restock && $item->product) {
                    $this->saleStock->restore($this->context->get(), $sale, $item->product, $qty, $item->conversion_qty, 'refund', $refund, $actor->id);
                }

                // Layanan yang dikembalikan penuh: komisinya batal.
                if (Qty::cmp($item->refunded_qty, $item->qty) >= 0) {
                    StaffCommission::query()->where('sale_item_id', $item->id)->where('status', 'pending')->update(['status' => 'canceled']);
                }
            }

            $refunded = $sale->refunded_amount + $amount;
            $sale->update([
                'refunded_amount' => $refunded,
                'payment_status' => $refunded >= $sale->total ? 'refunded' : 'partially_refunded',
            ]);

            activity('penjualan')->performedOn($sale)->causedBy($actor)
                ->withProperties(['alasan' => $reason, 'jumlah' => $amount, 'disetujui' => $approver?->name])
                ->log("Pengembalian {$refund->number} untuk nota {$sale->number}: ".Rupiah::format($amount));

            return $refund;
        });
    }
}
