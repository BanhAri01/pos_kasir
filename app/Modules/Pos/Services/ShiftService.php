<?php

namespace App\Modules\Pos\Services;

use App\Models\User;
use App\Modules\Pos\Models\CashMovement;
use App\Modules\Pos\Models\Refund;
use App\Modules\Pos\Models\SalePayment;
use App\Modules\Pos\Models\Shift;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Buka kasir (hitung uang awal di laci), uang masuk/keluar, dan tutup kasir
 * (bandingkan uang di laci dengan perhitungan aplikasi).
 */
class ShiftService
{
    /**
     * @param  bool  $reuseOpen  true (online): kalau orang ini masih punya kasir terbuka, pakai itu.
     *                           false (sinkron offline): kasir yang dibuka di HP punya uuid sendiri, tetap dibuat.
     */
    public function open(int $outletId, User $user, int $openingCash, ?string $uuid = null, ?int $deviceId = null, bool $reuseOpen = true, ?string $openedAt = null): Shift
    {
        if ($uuid && ($existing = Shift::query()->where('uuid', $uuid)->first())) {
            return $existing; // kirim ulang (idempotent)
        }

        // Online: satu orang hanya boleh punya satu kasir terbuka per outlet.
        if ($reuseOpen && ($open = $this->currentFor($user, $outletId))) {
            return $open;
        }

        return Shift::create([
            'outlet_id' => $outletId,
            'uuid' => $uuid ?? (string) Str::uuid(),
            'device_id' => $deviceId,
            'user_id' => $user->id,
            'opened_at' => $openedAt ?? now(),
            'opening_cash' => max(0, $openingCash),
            'status' => 'open',
        ]);
    }

    public function currentFor(User $user, int $outletId): ?Shift
    {
        return Shift::query()
            ->where('outlet_id', $outletId)
            ->where('user_id', $user->id)
            ->where('status', 'open')
            ->latest('id')
            ->first();
    }

    /** @param  bool  $allowClosed  catatan dari kasir offline tetap diterima walau kasir sudah ditutup */
    public function addCash(Shift $shift, string $type, int $amount, string $reason, User $user, ?string $uuid = null, bool $allowClosed = false): CashMovement
    {
        if ($uuid && ($existing = CashMovement::query()->where('uuid', $uuid)->first())) {
            return $existing;
        }

        if (! $allowClosed) {
            $this->ensureOpen($shift);
        }

        return CashMovement::create([
            'shift_id' => $shift->id,
            'uuid' => $uuid ?? (string) Str::uuid(),
            'type' => $type,
            'amount' => $amount,
            'reason' => $reason,
            'user_id' => $user->id,
        ]);
    }

    /**
     * Ringkasan uang di laci.
     *
     * @return array<string, mixed>
     */
    public function summary(Shift $shift): array
    {
        $payments = SalePayment::query()
            ->where('sale_payments.shift_id', $shift->id)
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->where('sales.status', 'completed')
            ->selectRaw('sale_payments.method_name, sale_payments.method_type, SUM(sale_payments.amount) as total, COUNT(*) as count')
            ->groupBy('sale_payments.method_name', 'sale_payments.method_type')
            ->get();

        $cashSales = (int) $payments->where('method_type', 'cash')->sum('total');
        $cashRefunds = (int) Refund::query()->where('shift_id', $shift->id)->where('method_type', 'cash')->sum('amount');
        $cashIn = (int) CashMovement::query()->where('shift_id', $shift->id)->where('type', 'in')->sum('amount');
        $cashOut = (int) CashMovement::query()->where('shift_id', $shift->id)->where('type', 'out')->sum('amount');

        $sales = $shift->sales()->where('status', 'completed');

        return [
            'opening_cash' => $shift->opening_cash,
            'cash_sales' => $cashSales,
            'cash_refunds' => $cashRefunds,
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'expected_cash' => $shift->opening_cash + $cashSales - $cashRefunds + $cashIn - $cashOut,
            'sales_count' => (clone $sales)->count(),
            'sales_total' => (int) (clone $sales)->sum('total'),
            'by_method' => $payments->map(fn ($p) => [
                'name' => $p->method_name,
                'type' => $p->method_type,
                'total' => (int) $p->total,
            ])->values()->all(),
        ];
    }

    public function close(Shift $shift, int $countedCash, ?string $note, User $user): Shift
    {
        return DB::transaction(function () use ($shift, $countedCash, $note, $user) {
            $shift = Shift::query()->lockForUpdate()->findOrFail($shift->id);
            $this->ensureOpen($shift);

            $expected = $this->summary($shift)['expected_cash'];

            $shift->update([
                'closed_at' => now(),
                'expected_cash' => $expected,
                'counted_cash' => $countedCash,
                'cash_difference' => $countedCash - $expected,
                'closing_note' => $note,
                'closed_by' => $user->id,
                'status' => 'closed',
            ]);

            if ($shift->cash_difference !== 0) {
                activity('kasir')->performedOn($shift)->causedBy($user)
                    ->withProperties(['selisih' => $shift->cash_difference])
                    ->log($shift->cash_difference < 0 ? 'Tutup kasir: uang kurang' : 'Tutup kasir: uang lebih');
            }

            return $shift;
        });
    }

    private function ensureOpen(Shift $shift): void
    {
        if (! $shift->isOpen()) {
            throw ValidationException::withMessages(['shift' => 'Kasir ini sudah ditutup.']);
        }
    }
}
