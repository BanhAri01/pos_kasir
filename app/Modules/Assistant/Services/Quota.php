<?php

namespace App\Modules\Assistant\Services;

use App\Models\Tenant;
use App\Modules\Assistant\Models\AssistantMessage;
use App\Modules\Assistant\Models\AssistantTopup;
use Illuminate\Support\Carbon;

/**
 * Pulsa Asisten Toko per bulan, dalam rupiah (biaya token asli).
 * Ditampilkan ke pemilik sebagai persen dan perkiraan sisa pertanyaan.
 */
class Quota
{
    public function __construct(private readonly Tenant $tenant) {}

    public static function for(Tenant $tenant): self
    {
        return new self($tenant);
    }

    public function base(): float
    {
        return (float) config($this->tenant->isOnTrial() ? 'hermes.assistant.trial_budget' : 'hermes.assistant.budget');
    }

    public function topups(): float
    {
        return (float) AssistantTopup::allTenants()
            ->where('tenant_id', $this->tenant->id)
            ->where('period', $this->now()->format('Y-m'))
            ->sum('amount');
    }

    public function budget(): float
    {
        return $this->base() + $this->topups();
    }

    public function used(): float
    {
        return $this->spentSince($this->now()->startOfMonth());
    }

    public function usedToday(): float
    {
        return $this->spentSince($this->now()->startOfDay());
    }

    public function dailyCap(): float
    {
        return max($this->base() * (float) config('hermes.assistant.daily_share'), (float) config('hermes.assistant.modes.deep.reserve'));
    }

    public function remaining(): float
    {
        return max(0.0, $this->budget() - $this->used());
    }

    public function percentLeft(): int
    {
        $budget = $this->budget();

        return $budget > 0 ? (int) floor($this->remaining() / $budget * 100) : 0;
    }

    public function questionsLeft(): int
    {
        return (int) floor($this->remaining() / max(1, (float) config('hermes.assistant.question_estimate')));
    }

    /** null = boleh; 'month' = pulsa bulan ini habis; 'day' = batas harian tercapai. */
    public function check(string $mode): ?string
    {
        $reserve = (float) config("hermes.assistant.modes.{$mode}.reserve");

        if ($this->remaining() < $reserve) {
            return 'month';
        }
        if ($this->dailyCap() - $this->usedToday() < $reserve) {
            return 'day';
        }

        return null;
    }

    public function period(): string
    {
        return $this->now()->format('Y-m');
    }

    private function now(): Carbon
    {
        return Carbon::now($this->tenant->timezone ?: config('app.timezone'));
    }

    private function spentSince(Carbon $from): float
    {
        return (float) AssistantMessage::allTenants()
            ->where('tenant_id', $this->tenant->id)
            ->where('created_at', '>=', $from->copy()->utc())
            ->sum('cost');
    }
}
