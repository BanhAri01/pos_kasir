<?php

namespace App\Modules\Assistant\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ClaudeLlm
{
    protected string $apiKey;
    protected string $model;

    public function __construct(
        protected AiTools $tools,
        ?string $apiKey = null,
        ?string $model = null
    ) {
        $this->apiKey = $apiKey ?? (string) config('services.anthropic.api_key', env('ANTHROPIC_API_KEY', ''));
        $this->model = $model ?? (string) config('services.anthropic.model', env('ANTHROPIC_MODEL', 'claude-3-5-haiku-20241022'));
    }

    public function isConfigured(): bool
    {
        return ! empty($this->apiKey);
    }

    public function generate(string $prompt, Tenant $tenant, array $history = []): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException("API Key Anthropic belum dikonfigurasi di ANTHROPIC_API_KEY.");
        }

        $systemPrompt = "Anda adalah Hermes POS AI, asisten analis toko dan kasir pintar berbasis data riil. " .
            "Nama toko/usaha: {$tenant->name}. " .
            "Tugas Anda adalah menjawab pertanyaan pemilik toko seputar omset, produk terlaris, stok menipis, kasir, laba rugi, dan kasbon. " .
            "Gunakan selalu tool yang relevan untuk mengambil data akurat dari database kasir sebelum menjawab. " .
            "Format jawaban dalam Bahasa Indonesia yang ramah, sopan, ringkas, memakai format tebal (*kata*) dan bullet points agar mudah dibaca di layar HP atau pesan WhatsApp.";

        $tools = AiTools::getToolDefinitions();
        $messages = [];

        // Sisipkan riwayat sebelumnya jika ada
        foreach ($history as $h) {
            $messages[] = [
                'role' => $h['sender_type'] === 'user' ? 'user' : 'assistant',
                'content' => $h['message'],
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => $prompt,
        ];

        $totalInputTokens = 0;
        $totalOutputTokens = 0;
        $toolsCalled = [];
        $maxLoops = 3;

        for ($i = 0; $i < $maxLoops; $i++) {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(30)->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
                'max_tokens' => 1024,
                'system' => $systemPrompt,
                'messages' => $messages,
                'tools' => $tools,
            ]);

            if (! $response->successful()) {
                Log::error('Claude API Error', ['status' => $response->status(), 'body' => $response->body()]);
                throw new RuntimeException("Gagal menghubungi AI Anthropic: " . ($response->json('error.message') ?? $response->body()));
            }

            $data = $response->json();
            $totalInputTokens += $data['usage']['input_tokens'] ?? 0;
            $totalOutputTokens += $data['usage']['output_tokens'] ?? 0;
            $stopReason = $data['stop_reason'] ?? '';

            // Cek apakah ada tool use
            $toolUseBlocks = [];
            $textContent = '';

            foreach ($data['content'] ?? [] as $block) {
                if ($block['type'] === 'text') {
                    $textContent .= $block['text'];
                } elseif ($block['type'] === 'tool_use') {
                    $toolUseBlocks[] = $block;
                }
            }

            if ($stopReason === 'tool_use' || ! empty($toolUseBlocks)) {
                // Tambahkan pesan assistant ke conversation
                $messages[] = [
                    'role' => 'assistant',
                    'content' => $data['content'],
                ];

                // Eksekusi setiap tool
                $toolResultBlocks = [];
                foreach ($toolUseBlocks as $toolBlock) {
                    $toolName = $toolBlock['name'];
                    $toolInput = $toolBlock['input'] ?? [];
                    $toolResult = $this->tools->execute($toolName, $toolInput, $tenant);

                    $toolsCalled[] = [
                        'name' => $toolName,
                        'input' => $toolInput,
                        'output' => $toolResult,
                    ];

                    $toolResultBlocks[] = [
                        'type' => 'tool_result',
                        'tool_use_id' => $toolBlock['id'],
                        'content' => json_encode($toolResult, JSON_UNESCAPED_UNICODE),
                    ];
                }

                // Kirim hasil tool kembali ke Claude
                $messages[] = [
                    'role' => 'user',
                    'content' => $toolResultBlocks,
                ];

                // Lanjut loop untuk mendapatkan jawaban final
                continue;
            }

            // Jika tidak ada tool use lagi, kembalikan jawaban akhir
            return [
                'text' => trim($textContent),
                'tools_called' => $toolsCalled,
                'input_tokens' => $totalInputTokens,
                'output_tokens' => $totalOutputTokens,
                'model' => $this->model,
            ];
        }

        return [
            'text' => trim($textContent),
            'tools_called' => $toolsCalled,
            'input_tokens' => $totalInputTokens,
            'output_tokens' => $totalOutputTokens,
            'model' => $this->model,
        ];
    }
}
