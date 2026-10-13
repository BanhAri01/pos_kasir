<?php

namespace App\Modules\Assistant\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Assistant\Models\AssistantMessage;
use Illuminate\Support\Facades\Log;

class AiAgent
{
    public function __construct(
        protected AiTools $tools,
        protected AiQuota $quota,
        protected DemoLlm $demoLlm,
        protected ClaudeLlm $claudeLlm
    ) {}

    public function ask(
        string $prompt,
        Tenant $tenant,
        ?User $user = null,
        string $channel = 'web',
        ?string $senderPhone = null,
        ?string $driver = null
    ): AssistantMessage {
        // 1. Cek Kuota
        if (! $this->quota->canAsk($tenant)) {
            $warningText = "⚠️ *Batas Kuota Harian AI Tercapai*\n\nAnda telah mencapai batas 100 pertanyaan AI untuk hari ini. Kuota akan direset otomatis besok pagi pukul 00:00.";

            // Simpan pertanyaan user
            AssistantMessage::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user?->id,
                'sender_type' => 'user',
                'channel' => $channel,
                'sender_phone' => $senderPhone,
                'message' => $prompt,
                'tokens_used' => 0,
                'cost_idr' => 0,
            ]);

            return AssistantMessage::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user?->id,
                'sender_type' => 'assistant',
                'channel' => $channel,
                'sender_phone' => $senderPhone,
                'message' => $warningText,
                'tokens_used' => 0,
                'cost_idr' => 0,
            ]);
        }

        // Simpan pesan user
        AssistantMessage::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user?->id,
            'sender_type' => 'user',
            'channel' => $channel,
            'sender_phone' => $senderPhone,
            'message' => $prompt,
            'tokens_used' => 0,
            'cost_idr' => 0,
        ]);

        // 2. Pilih Driver LLM
        $useClaude = ($driver === 'claude' || ($driver === null && $this->claudeLlm->isConfigured()));
        $result = null;

        if ($useClaude) {
            try {
                $history = AssistantMessage::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('channel', $channel)
                    ->orderBy('created_at', 'desc')
                    ->limit(6)
                    ->get()
                    ->reverse()
                    ->values()
                    ->toArray();

                $result = $this->claudeLlm->generate($prompt, $tenant, $history);
            } catch (\Throwable $e) {
                Log::warning('ClaudeLlm failed, falling back to DemoLlm', ['error' => $e->getMessage()]);
                $result = null;
            }
        }

        // Fallback ke DemoLlm (zero cost & membaca data riil DB)
        if (! $result) {
            $result = $this->demoLlm->generate($prompt, $tenant);
        }

        $inputTokens = $result['input_tokens'] ?? 0;
        $outputTokens = $result['output_tokens'] ?? 0;
        $totalTokens = $inputTokens + $outputTokens;
        $model = $result['model'] ?? 'demo';

        $costIdr = AiPricing::calculateCost($model, $inputTokens, $outputTokens);

        // 3. Catat Kuota & Biaya
        $this->quota->recordUsage($tenant, $totalTokens, $costIdr);

        // 4. Simpan Respon Asisten
        return AssistantMessage::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user?->id,
            'sender_type' => 'assistant',
            'channel' => $channel,
            'sender_phone' => $senderPhone,
            'message' => $result['text'],
            'tools_called' => $result['tools_called'] ?? [],
            'tokens_used' => $totalTokens,
            'cost_idr' => $costIdr,
        ]);
    }
}
