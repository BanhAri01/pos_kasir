<?php

namespace App\Modules\Loyalty\Services;

use App\Core\Support\Rupiah;
use App\Core\Tenancy\TenantContext;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Loyalty\Models\LoyaltyEntry;
use App\Modules\Pos\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class LoyaltyService
{
    public const DEFAULTS = [
        'spend_per_point' => 10000,
        'points_for_reward' => 10,
        'reward_value' => 10000,
        'notify' => false,
    ];

    public function __construct(private TenantContext $context) {}

    public static function settings(Tenant $tenant): array
    {
        $saved = $tenant->settings['loyalty'] ?? [];

        return [
            'spend_per_point' => max(1000, (int) ($saved['spend_per_point'] ?? self::DEFAULTS['spend_per_point'])),
            'points_for_reward' => max(1, (int) ($saved['points_for_reward'] ?? self::DEFAULTS['points_for_reward'])),
            'reward_value' => max(500, (int) ($saved['reward_value'] ?? self::DEFAULTS['reward_value'])),
            'notify' => (bool) ($saved['notify'] ?? self::DEFAULTS['notify']),
        ];
    }

    public static function describe(array $settings): string
    {
        return sprintf(
            'Setiap belanja %s dapat 1 poin. %d poin bisa ditukar potongan %s.',
            Rupiah::format($settings['spend_per_point']),
            $settings['points_for_reward'],
            Rupiah::format($settings['reward_value']),
        );
    }

    public function pointsFor(int $amount, array $settings): int
    {
        return intdiv(max(0, $amount), $settings['spend_per_point']);
    }

    public function applySale(Sale $sale, array $data, bool $strict): int
    {
        if (! $sale->customer_id) {
            return 0;
        }

        $settings = self::settings($this->context->get());
        $redeem = max(0, (int) ($data['redeem_points'] ?? 0));

        return DB::transaction(function () use ($sale, $settings, $redeem, $strict) {
            $customer = Customer::withTrashed()->whereKey($sale->customer_id)->lockForUpdate()->firstOrFail();

            if ($redeem > 0 && ! LoyaltyEntry::query()->where('sale_id', $sale->id)->where('type', 'redeem')->exists()) {
                $this->redeem($customer, $sale, $redeem, $settings, $strict);
            }

            $earned = $this->pointsFor($sale->total - $sale->due_amount, $settings);
            if ($earned > 0 && ! LoyaltyEntry::query()->where('sale_id', $sale->id)->where('type', 'earn')->exists()) {
                $this->book($customer, $earned, 'earn', "Belanja nota {$sale->number}", $sale->id, $sale->cashier_id);
            }

            return $earned;
        });
    }

    public function reverseSale(Sale $sale): void
    {
        DB::transaction(function () use ($sale) {
            $entries = LoyaltyEntry::query()->where('sale_id', $sale->id)->whereIn('type', ['earn', 'redeem'])->get();
            if ($entries->isEmpty() || LoyaltyEntry::query()->where('sale_id', $sale->id)->where('type', 'reverse')->exists()) {
                return;
            }

            $customer = Customer::withTrashed()->whereKey($entries->first()->customer_id)->lockForUpdate()->firstOrFail();
            $this->book($customer, -$entries->sum('points'), 'reverse', "Nota {$sale->number} dibatalkan", $sale->id, null);
        });
    }

    public function adjust(Customer $customer, int $points, string $note, User $actor): LoyaltyEntry
    {
        return DB::transaction(function () use ($customer, $points, $note, $actor) {
            $customer = Customer::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();
            if ($customer->loyalty_points + $points < 0) {
                throw ValidationException::withMessages(['points' => "Poin {$customer->name} hanya {$customer->loyalty_points}."]);
            }

            return $this->book($customer, $points, 'adjust', $note, null, $actor->id);
        });
    }

    private function redeem(Customer $customer, Sale $sale, int $points, array $settings, bool $strict): void
    {
        $valid = $points % $settings['points_for_reward'] === 0;
        $enough = $customer->loyalty_points >= $points;
        $value = intdiv($points, $settings['points_for_reward']) * $settings['reward_value'];
        $covered = $sale->discount_amount >= min($value, $sale->subtotal);

        if ($strict && (! $valid || ! $enough || ! $covered)) {
            throw ValidationException::withMessages(['redeem_points' => match (true) {
                ! $enough => "Poin {$customer->name} tidak cukup (punya {$customer->loyalty_points} poin).",
                ! $valid => "Poin ditukar per {$settings['points_for_reward']} poin.",
                default => 'Potongan tukar poin belum masuk ke diskon. Coba tukar poin lagi.',
            }]);
        }

        if (! $valid || ! $enough) {
            Log::warning("Tukar poin dari kasir offline tidak sesuai aturan: nota {$sale->number}, {$points} poin, saldo {$customer->loyalty_points}.");
        }

        $this->book($customer, -$points, 'redeem', "Tukar poin nota {$sale->number}", $sale->id, $sale->cashier_id);
    }

    private function book(Customer $customer, int $points, string $type, string $note, ?int $saleId, ?int $userId): LoyaltyEntry
    {
        $entry = LoyaltyEntry::create([
            'customer_id' => $customer->id,
            'sale_id' => $saleId,
            'type' => $type,
            'points' => $points,
            'note' => mb_substr($note, 0, 200),
            'created_by' => $userId,
        ]);

        $customer->forceFill(['loyalty_points' => $customer->loyalty_points + $points])->save();

        return $entry;
    }
}
