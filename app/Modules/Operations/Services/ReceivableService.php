<?php

namespace App\Modules\Operations\Services;

use App\Core\Support\Rupiah;
use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Operations\Models\Receivable;
use App\Modules\Operations\Models\ReceivablePayment;
use App\Modules\Pos\Models\PaymentMethod;
use App\Modules\Pos\Models\Sale;
use App\Modules\WhatsApp\Services\WhatsAppService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Kasbon & piutang: catat utang manual, terima cicilan/pelunasan, dan kirim pengingat WhatsApp.
 */
class ReceivableService
{
    public function __construct(private WhatsAppService $whatsapp) {}

    /** Catat utang tanpa transaksi (mis. "pinjam uang" atau saldo utang lama dari buku). */
    public function recordManual(Customer $customer, int $amount, ?string $note, ?string $dueDate = null): Receivable
    {
        return Receivable::create([
            'customer_id' => $customer->id,
            'type' => 'kasbon',
            'amount' => $amount,
            'paid_amount' => 0,
            'due_date' => $dueDate,
            'status' => 'open',
            'note' => $note,
        ]);
    }

    /**
     * Terima pembayaran utang pelanggan. Dibagikan ke utang yang paling lama dulu.
     * Idempotent dengan uuid (aman dikirim ulang dari kasir offline).
     */
    public function pay(Customer $customer, int $amount, ?int $paymentMethodId, User $user, ?int $shiftId = null, ?string $uuid = null): int
    {
        $uuid ??= (string) Str::uuid();

        if (ReceivablePayment::query()->where('uuid', $uuid)->exists()) {
            return 0;
        }

        return DB::transaction(function () use ($customer, $amount, $paymentMethodId, $user, $shiftId, $uuid) {
            $open = Receivable::query()->where('customer_id', $customer->id)->where('status', 'open')
                ->orderBy('created_at')->orderBy('id')->lockForUpdate()->get();

            $balance = (int) $open->sum(fn ($r) => $r->amount - $r->paid_amount);
            if ($balance <= 0) {
                throw ValidationException::withMessages(['amount' => "{$customer->name} tidak punya utang."]);
            }
            if ($amount > $balance) {
                throw ValidationException::withMessages(['amount' => 'Pembayaran melebihi utang. Sisa utang '.Rupiah::format($balance).'.']);
            }

            $method = $paymentMethodId ? PaymentMethod::query()->find($paymentMethodId) : PaymentMethod::query()->where('type', 'cash')->first();
            $remaining = $amount;
            $first = true;

            foreach ($open as $receivable) {
                if ($remaining <= 0) {
                    break;
                }
                $portion = min($remaining, $receivable->amount - $receivable->paid_amount);

                ReceivablePayment::create([
                    'receivable_id' => $receivable->id,
                    'uuid' => $first ? $uuid : (string) Str::uuid(),
                    'amount' => $portion,
                    'payment_method_id' => $method?->id,
                    'method_type' => $method?->type ?? 'cash',
                    'shift_id' => $shiftId,
                    'user_id' => $user->id,
                    'paid_at' => now(),
                ]);

                $paid = $receivable->paid_amount + $portion;
                $receivable->update(['paid_amount' => $paid, 'status' => $paid >= $receivable->amount ? 'paid' : 'open']);
                $this->syncSale($receivable->sale_id);

                $remaining -= $portion;
                $first = false;
            }

            activity('kasbon')->performedOn($customer)->causedBy($user)->log("Terima pembayaran utang {$customer->name}: ".Rupiah::format($amount));

            return $balance - $amount;
        });
    }

    public function remind(Customer $customer): bool
    {
        $balance = $customer->outstandingBalance();
        if ($balance <= 0 || ! $customer->phone) {
            return false;
        }

        $this->whatsapp->sendTemplate('kasbon_reminder', $customer->phone, [
            'nama' => $customer->name,
            'sisa' => Rupiah::format($balance),
        ], $customer);

        Receivable::query()->where('customer_id', $customer->id)->where('status', 'open')->update(['last_reminded_at' => now()]);

        return true;
    }

    /** Nota yang utangnya lunas ikut berubah status pembayarannya. */
    private function syncSale(?int $saleId): void
    {
        if (! $saleId || ! ($sale = Sale::query()->find($saleId))) {
            return;
        }

        $receivable = Receivable::query()->where('sale_id', $saleId)->first();
        $due = max(0, $receivable->amount - $receivable->paid_amount);

        $sale->update([
            'due_amount' => $due,
            'payment_status' => $due === 0 ? 'paid' : 'partial',
        ]);
    }
}
