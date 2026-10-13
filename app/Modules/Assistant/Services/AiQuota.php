<?php

namespace App\Modules\Assistant\Services;

use App\Models\Tenant;
use App\Modules\Assistant\Models\AssistantDailyUsage;
use Carbon\Carbon;

class AiQuota
{
    public const DAILY_MESSAGE_LIMIT = 100;

    public function canAsk(Tenant $tenant): bool
    {
        $today = Carbon::today()->toDateString();
        $usage = AssistantDailyUsage::query()
            ->where('tenant_id', $tenant->id)
            ->where('date', $today)
            ->first();

        if (! $usage) {
            return true;
        }

        return $usage->total_messages < self::DAILY_MESSAGE_LIMIT;
    }

    public function recordUsage(Tenant $tenant, int $tokens, float $costIdr): AssistantDailyUsage
    {
        $today = Carbon::today()->toDateString();

        $usage = AssistantDailyUsage::firstOrCreate(
            ['tenant_id' => $tenant->id, 'date' => $today],
            ['total_messages' => 0, 'total_tokens' => 0, 'cost_idr' => 0]
        );

        $usage->increment('total_messages', 1);
        $usage->increment('total_tokens', $tokens);
        $usage->cost_idr = (float) $usage->cost_idr + $costIdr;
        $usage->save();

        return $usage;
    }

    public function getTodayStats(Tenant $tenant): array
    {
        $today = Carbon::today()->toDateString();
        $usage = AssistantDailyUsage::query()
            ->where('tenant_id', $tenant->id)
            ->where('date', $today)
            ->first();

        $messages = $usage ? $usage->total_messages : 0;
        $tokens = $usage ? $usage->total_tokens : 0;
        $cost = $usage ? (float) $usage->cost_idr : 0.0;

        return [
            'messages_today' => $messages,
            'messages_limit' => self::DAILY_MESSAGE_LIMIT,
            'remaining_messages' => max(0, self::DAILY_MESSAGE_LIMIT - $messages),
            'tokens_today' => $tokens,
            'cost_today_idr' => $cost,
        ];
    }
}
