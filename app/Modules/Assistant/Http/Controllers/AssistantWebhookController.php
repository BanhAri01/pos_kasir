<?php

namespace App\Modules\Assistant\Http\Controllers;

use App\Core\Support\Phone;
use App\Http\Controllers\Controller;
use App\Modules\Assistant\Jobs\AnswerAssistantWhatsApp;
use App\Modules\Assistant\Models\AssistantMessage;
use App\Modules\Assistant\Models\AssistantPhone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Pesan WhatsApp masuk dari Fonnte untuk Asisten Toko.
 *
 * URL: POST /webhook/asisten/{rahasia} (isi di Fonnte > Device > Webhook).
 *  - rahasia URL wajib cocok, selain itu 404
 *  - nomor device dicocokkan bila HERMES_AI_WA_DEVICE diisi
 *  - usaha ditentukan dari nomor pengirim yang didaftarkan pemilik, bukan dari isi pesan
 *  - nomor tak dikenal, pesan grup, dan pesan dobel diabaikan tanpa balasan (tidak memakan biaya)
 */
class AssistantWebhookController extends Controller
{
    private const PER_MINUTE = 10;

    public function __invoke(Request $request, string $secret): JsonResponse
    {
        $expected = (string) config('hermes.assistant.webhook_secret');
        abort_if($expected === '' || ! hash_equals($expected, $secret), 404);

        $device = Phone::normalize((string) config('hermes.assistant.wa_device'));
        if ($device && Phone::normalize((string) $request->input('device')) !== $device) {
            return $this->ignored('device');
        }

        $rawSender = (string) $request->input('sender');
        if (filled($request->input('member')) || str_contains($rawSender, '@g.us') || str_contains($rawSender, '-')) {
            return $this->ignored('group');
        }

        $sender = Phone::normalize($rawSender);
        $text = trim((string) $request->input('message', ''));
        if (! $sender || $text === '') {
            return $this->ignored('empty');
        }

        $phone = AssistantPhone::allTenants()->where('phone', $sender)->first();
        if (! $phone) {
            return $this->ignored('unknown');
        }

        $id = $request->input('inboxid') ?? $request->input('id');
        $externalId = filled($id) ? 'fonnte:'.mb_substr((string) $id, 0, 90) : null;
        if ($externalId && AssistantMessage::allTenants()->where('external_id', $externalId)->exists()) {
            return $this->ignored('duplicate');
        }

        $key = 'asisten-wa:'.$sender;
        if (RateLimiter::tooManyAttempts($key, self::PER_MINUTE)) {
            return $this->ignored('rate');
        }
        RateLimiter::hit($key, 60);

        AnswerAssistantWhatsApp::dispatch($phone->id, mb_substr($text, 0, 2000), $externalId);

        return response()->json(['status' => 'accepted']);
    }

    private function ignored(string $reason): JsonResponse
    {
        return response()->json(['status' => 'ignored', 'reason' => $reason]);
    }
}
