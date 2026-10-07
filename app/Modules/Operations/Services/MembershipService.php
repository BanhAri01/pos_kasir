<?php

namespace App\Modules\Operations\Services;

use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Operations\Models\MemberCheckin;
use App\Modules\Operations\Models\Membership;
use App\Modules\Operations\Models\MembershipPlan;
use App\Modules\WhatsApp\Services\WhatsAppService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/** Member: aktifkan / perpanjang paket, cek status, dan absen masuk (check-in). */
class MembershipService
{
    /**
     * Aktifkan paket untuk pelanggan. Kalau masih punya paket sejenis yang aktif,
     * masa aktif baru disambung setelah paket lama habis (perpanjangan).
     */
    public function activate(Customer $customer, MembershipPlan $plan, ?int $saleId, string $timezone): Membership
    {
        $today = Carbon::now($timezone)->startOfDay();

        $latest = Membership::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'active')
            ->whereHas('plan', fn ($q) => $q->where('kind', $plan->kind))
            ->whereDate('ends_on', '>=', $today->toDateString())
            ->orderByDesc('ends_on')
            ->first();

        $start = $latest ? $latest->ends_on->copy()->addDay() : $today;
        $end = (match ($plan->duration_unit) {
            'day' => $start->copy()->addDays($plan->duration_value),
            'week' => $start->copy()->addWeeks($plan->duration_value),
            'month' => $start->copy()->addMonthsNoOverflow($plan->duration_value),
            default => $start->copy()->addYearsNoOverflow($plan->duration_value),
        })->subDay();

        return Membership::create([
            'customer_id' => $customer->id,
            'membership_plan_id' => $plan->id,
            'sale_id' => $saleId,
            'starts_on' => $start->toDateString(),
            'ends_on' => $end->toDateString(),
            'sessions_remaining' => $plan->session_quota,
            'status' => 'active',
        ]);
    }

    /** Paket yang sedang berlaku hari ini (null = tidak aktif / kedaluwarsa). */
    public function activeFor(Customer $customer, string $timezone, ?string $kind = null): ?Membership
    {
        $today = Carbon::now($timezone)->toDateString();

        return Membership::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'active')
            ->whereDate('starts_on', '<=', $today)
            ->whereDate('ends_on', '>=', $today)
            ->when($kind, fn ($q) => $q->whereHas('plan', fn ($p) => $p->where('kind', $kind)))
            ->with('plan.product:id,name')
            ->orderByDesc('ends_on')
            ->first();
    }

    public function checkIn(Customer $customer, int $outletId, User $user, string $timezone, string $method = 'manual', bool $usePtSession = false): MemberCheckin
    {
        $membership = $this->activeFor($customer, $timezone, $usePtSession ? 'pt_session' : null);

        if (! $membership) {
            throw ValidationException::withMessages([
                'member' => "{$customer->name} tidak punya paket yang aktif. Tawarkan perpanjangan di kasir.",
            ]);
        }

        if ($usePtSession) {
            if (($membership->sessions_remaining ?? 0) <= 0) {
                throw ValidationException::withMessages(['member' => 'Sesi personal trainer sudah habis.']);
            }
            $membership->decrement('sessions_remaining');
        }

        return MemberCheckin::create([
            'outlet_id' => $outletId,
            'membership_id' => $membership->id,
            'customer_id' => $customer->id,
            'method' => $method,
            'user_id' => $user->id,
            'checked_in_at' => now(),
        ]);
    }

    /** Kabari member yang paketnya habis 3 hari lagi (sekali per paket). */
    public function sendExpiringReminders(string $timezone): int
    {
        $whatsapp = app(WhatsAppService::class);
        $target = Carbon::now($timezone)->addDays(3)->toDateString();
        $count = 0;

        Membership::query()->where('status', 'active')->whereNull('reminder_sent_at')->whereDate('ends_on', '<=', $target)
            ->whereDate('ends_on', '>=', Carbon::now($timezone)->toDateString())
            ->with('customer')->get()
            ->each(function (Membership $membership) use ($whatsapp, &$count) {
                // Sudah diperpanjang (ada paket lanjutan)? Tidak perlu diingatkan.
                $renewed = Membership::query()->where('customer_id', $membership->customer_id)->where('status', 'active')
                    ->whereDate('ends_on', '>', $membership->ends_on)->exists();
                if (! $renewed && $membership->customer?->phone) {
                    $whatsapp->sendTemplate('membership_expiring', $membership->customer->phone, [
                        'nama' => $membership->customer->name,
                        'tanggal' => $membership->ends_on->locale('id')->translatedFormat('j F Y'),
                    ], $membership);
                    $count++;
                }
                $membership->update(['reminder_sent_at' => now()]);
            });

        return $count;
    }
}
