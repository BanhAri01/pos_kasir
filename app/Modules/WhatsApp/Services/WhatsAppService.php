<?php

namespace App\Modules\WhatsApp\Services;

use App\Core\Support\Phone;
use App\Core\Tenancy\TenantContext;
use App\Modules\WhatsApp\Jobs\SendWhatsAppMessage;
use App\Modules\WhatsApp\Models\MessageTemplate;
use App\Modules\WhatsApp\Models\WhatsappMessage;
use Illuminate\Database\Eloquent\Model;

/**
 * Kirim pesan WhatsApp lewat antrean (tidak membuat kasir menunggu), dengan template yang bisa
 * diubah pemilik. Isian {nama}, {usaha}, {total}, dst. diganti otomatis.
 */
class WhatsAppService
{
    /** Template bawaan (bahasa sehari-hari, sopan). */
    public const DEFAULT_TEMPLATES = [
        'receipt' => "Halo {nama}, terima kasih sudah berbelanja di *{usaha}* 🙏\n\nNo. Nota: {nota}\nTotal: *{total}*\n\nLihat struk: {link}",
        'kasbon_reminder' => "Halo {nama}, ini pengingat dari *{usaha}*.\n\nCatatan utang Anda saat ini: *{sisa}*.\nMohon dilunasi bila sudah ada rezeki. Terima kasih 🙏",
        'order_ready' => "Halo {nama}, pesanan Anda di *{usaha}* (nota {nota}) sudah *{status}* ✅\n\n{sisa}\n\nTerima kasih!",
        'booking_reminder' => "Halo {nama}, mengingatkan janji Anda di *{usaha}* hari ini jam *{jam}* untuk {layanan}.\n\nSampai jumpa! 🙏",
        'membership_expiring' => "Halo {nama}, paket member Anda di *{usaha}* akan habis pada *{tanggal}*.\n\nYuk perpanjang supaya tetap bisa latihan 💪",
    ];

    public const LABELS = [
        'receipt' => 'Struk belanja',
        'kasbon_reminder' => 'Pengingat utang / kasbon',
        'order_ready' => 'Pesanan siap diambil',
        'booking_reminder' => 'Pengingat janji temu',
        'membership_expiring' => 'Paket member hampir habis',
    ];

    public function __construct(private TenantContext $context) {}

    public function template(string $code): string
    {
        return MessageTemplate::query()->where('code', $code)->where('is_active', true)->value('body')
            ?? self::DEFAULT_TEMPLATES[$code]
            ?? '';
    }

    public function render(string $body, array $vars): string
    {
        $vars = ['usaha' => $this->context->get()?->name, ...$vars];

        return trim(preg_replace_callback('/\{(\w+)\}/', fn ($m) => (string) ($vars[$m[1]] ?? ''), $body));
    }

    public function sendTemplate(string $code, string $phone, array $vars, ?Model $related = null): ?WhatsappMessage
    {
        return $this->send($phone, $this->render($this->template($code), $vars), $code, $related);
    }

    /**
     * Simpan pesan lalu kirim lewat antrean, SETELAH transaksi database selesai
     * (supaya tidak ada pesan untuk transaksi yang batal).
     */
    public function send(string $phone, string $body, ?string $templateCode = null, ?Model $related = null): ?WhatsappMessage
    {
        $to = Phone::normalize($phone);
        if (! $to || $body === '') {
            return null;
        }

        $message = WhatsappMessage::create([
            'to_phone' => $to,
            'template_code' => $templateCode,
            'body' => $body,
            'status' => 'queued',
            'related_type' => $related?->getMorphClass(),
            'related_id' => $related?->getKey(),
        ]);

        SendWhatsAppMessage::dispatch($message->id, $this->context->id())->afterCommit();

        return $message;
    }
}
