<?php

namespace App\Modules\WhatsApp\Services;

use App\Core\Support\Phone;
use App\Core\Tenancy\TenantContext;
use App\Modules\Billing\Services\PlanGuard;
use App\Modules\WhatsApp\Jobs\SendWhatsAppMessage;
use App\Modules\WhatsApp\Models\MessageTemplate;
use App\Modules\WhatsApp\Models\WhatsappMessage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Kirim pesan WhatsApp lewat antrean (tidak membuat kasir menunggu), dengan template yang bisa
 * diubah pemilik. Isian {nama}, {usaha}, {total}, dst. diganti otomatis.
 */
class WhatsAppService
{
    /** Template bawaan (bahasa sehari-hari, sopan). */
    public const DEFAULT_TEMPLATES = [
        'receipt' => "Halo {nama}, terima kasih sudah berbelanja di *{usaha}* 🙏\n\nNo. Nota: {nota}\nTotal: *{total}*\n\nLihat struk: {link}",
        'kasbon_reminder' => "Yth. Bapak/Ibu {nama},\n\nBersama pesan ini kami sampaikan tagihan dari *{usaha}* per {tanggal}.\n\nTagihan jatuh tempo: *{tagihan}*\nJatuh tempo: {jatuh_tempo}\nTotal sisa tagihan: *{sisa}* ({jumlah_nota} nota)\n\nRincian nota dapat dilihat, dan bukti pembayaran dapat diunggah, melalui tautan berikut:\n{link}\n\nMohon abaikan pesan ini apabila pembayaran sudah dilakukan. Terima kasih atas kerja samanya 🙏",
        'kasbon_proof' => "*Bukti bayar kasbon masuk*\n\n{nama} mengirim bukti pembayaran *{jumlah}*.\nSisa tagihan sebelum dicek: {sisa}\n\nCek lalu terima atau tolak di sini:\n{link}",
        'kasbon_paid' => "Yth. Bapak/Ibu {nama},\n\nPembayaran sebesar *{jumlah}* sudah kami terima. Terima kasih 🙏\nSisa tagihan: *{sisa}*\n\nHormat kami,\n*{usaha}*",
        'kasbon_rejected' => "Yth. Bapak/Ibu {nama},\n\nMohon maaf, bukti pembayaran sebesar *{jumlah}* belum dapat kami terima.\nAlasan: {alasan}\n\nSilakan kirim ulang bukti pembayaran melalui tautan berikut:\n{link}\n\nHormat kami,\n*{usaha}*",
        'order_ready' => "Halo {nama}, pesanan Anda di *{usaha}* (nota {nota}) sudah *{status}* ✅\n\n{sisa}\n\nTerima kasih!",
        'booking_reminder' => "Halo {nama}, mengingatkan janji Anda di *{usaha}* hari ini jam *{jam}* untuk {layanan}.\n\nSampai jumpa! 🙏",
        'membership_expiring' => "Halo {nama}, paket member Anda di *{usaha}* akan habis pada *{tanggal}*.\n\nYuk perpanjang supaya tetap bisa latihan 💪",
        'loyalty_points' => "Halo {nama}, terima kasih sudah belanja di *{usaha}* 🙏\n\nAnda dapat *{poin} poin*. Total poin sekarang: *{total}*.\nKumpulkan {target} poin untuk potongan {hadiah}.",
        'daily_report' => "*Laporan {usaha}*\n{isi}",
    ];

    public const LABELS = [
        'receipt' => 'Struk belanja',
        'kasbon_reminder' => 'Tagihan kasbon ke pelanggan',
        'kasbon_proof' => 'Bukti bayar kasbon masuk (ke pemilik)',
        'kasbon_paid' => 'Pembayaran kasbon diterima',
        'kasbon_rejected' => 'Bukti bayar kasbon ditolak',
        'order_ready' => 'Pesanan siap diambil',
        'booking_reminder' => 'Pengingat janji temu',
        'membership_expiring' => 'Paket member hampir habis',
        'loyalty_points' => 'Poin pelanggan bertambah',
        'daily_report' => 'Laporan harian ke pemilik',
    ];

    public function __construct(private TenantContext $context, private PlanGuard $guard) {}

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

        $tenant = $this->context->get();
        if ($tenant && $this->guard->whatsappQuotaLeft($tenant) === 0) {
            Log::info("Kuota WhatsApp usaha {$tenant->id} bulan ini habis, pesan tidak dikirim.");

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
