<?php

namespace App\Modules\Assistant\Http\Controllers;

use App\Core\Support\Phone;
use App\Http\Controllers\Controller;
use App\Modules\Assistant\Jobs\AnswerAssistantWhatsApp;
use App\Modules\Assistant\Models\TenantAssistantPhone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AssistantWebhookController extends Controller
{
    public function webhook(Request $request): JsonResponse
    {
        // Fonnte webhook payload: sender, message
        $rawSender = $request->input('sender') ?? $request->input('phone') ?? $request->input('from');
        $rawMessage = $request->input('message') ?? $request->input('text');

        if (! $rawSender || ! $rawMessage) {
            return response()->json(['status' => false, 'message' => 'Sender dan message wajib diisi.'], 400);
        }

        $sender = Phone::normalize($rawSender);

        if (! $sender) {
            return response()->json(['status' => false, 'message' => 'Format nomor sender tidak valid.'], 422);
        }

        // Cari tenant berdasarkan nomor yang terdaftar
        $authorizedPhone = TenantAssistantPhone::query()
            ->with('tenant')
            ->where('phone_number', $sender)
            ->where('is_active', true)
            ->first();

        if (! $authorizedPhone || ! $authorizedPhone->tenant) {
            Log::warning('Pesan AI WhatsApp diterima dari nomor tidak terdaftar', ['sender' => $sender]);

            return response()->json([
                'status' => false,
                'message' => 'Nomor WhatsApp Anda belum didaftarkan di sistem Hermes POS AI.',
            ], 403);
        }

        // Dispatch job untuk menjawab pertanyaan melalui WhatsApp
        AnswerAssistantWhatsApp::dispatch(
            $authorizedPhone->tenant,
            $sender,
            trim($rawMessage)
        );

        return response()->json([
            'status' => true,
            'message' => 'Pertanyaan sedang diproses oleh Hermes POS AI.',
        ]);
    }
}
