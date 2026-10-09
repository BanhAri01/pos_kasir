<?php

namespace App\Modules\Operations\Services;

use App\Core\Support\Rupiah;
use App\Core\Tenancy\TenantContext;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Operations\Models\Receivable;
use App\Modules\Operations\Models\ReceivableProof;
use App\Modules\Report\Services\OwnerReportService;
use App\Modules\WhatsApp\Services\WhatsAppService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class KasbonBillService
{
    public const DISK = 'local';

    public const REPEAT_DAYS = 7;

    public const SEND_HOURS = [8, 18];

    public const MAX_PENDING = 3;

    private const MAX_IMAGE = 1600;

    public function __construct(
        private TenantContext $context,
        private WhatsAppService $whatsapp,
        private ReceivableService $receivables,
        private OwnerReportService $owner,
    ) {}

    public static function settings(Tenant $tenant): array
    {
        $saved = $tenant->settings['kasbon_bill'] ?? [];

        return [
            'auto' => (bool) ($saved['auto'] ?? false),
            'payment_info' => (string) ($saved['payment_info'] ?? ''),
        ];
    }

    public function saveSettings(Tenant $tenant, bool $auto, ?string $paymentInfo): void
    {
        $settings = $tenant->settings ?? [];
        $settings['kasbon_bill'] = ['auto' => $auto, 'payment_info' => trim((string) $paymentInfo)];
        $tenant->forceFill(['settings' => $settings])->save();
    }

    public static function nextBillingDate(int $day, Carbon $today): Carbon
    {
        $start = $today->copy()->startOfDay();
        $date = $start->copy()->setDay(min($day, $start->daysInMonth));

        if ($date->gt($start)) {
            return $date;
        }

        $next = $start->copy()->startOfMonth()->addMonthNoOverflow();

        return $next->setDay(min($day, $next->daysInMonth));
    }

    public static function defaultDueDate(?Customer $customer, string $timezone): ?string
    {
        return $customer?->billing_day ? self::nextBillingDate($customer->billing_day, now($timezone))->toDateString() : null;
    }

    public function setBillingDay(Customer $customer, ?int $day): void
    {
        DB::transaction(function () use ($customer, $day) {
            $customer->update(['billing_day' => $day]);

            if ($day) {
                Receivable::query()->where('customer_id', $customer->id)->where('status', 'open')->whereNull('due_date')
                    ->update(['due_date' => self::defaultDueDate($customer, $this->context->get()->timezone)]);
            }
        });
    }

    public function billUrl(Customer $customer): string
    {
        if (! $customer->bill_token) {
            $customer->forceFill(['bill_token' => Str::random(40)])->save();
        }

        return route('kasbon.bill', $customer->bill_token);
    }

    public function resetLink(Customer $customer): void
    {
        $customer->forceFill(['bill_token' => Str::random(40)])->save();
    }

    public function summary(Customer $customer, Carbon $today): array
    {
        $open = Receivable::query()->where('customer_id', $customer->id)->where('status', 'open')
            ->with('sale:id,uuid,number')->orderBy('created_at')->orderBy('id')->get();

        $remaining = fn (Receivable $r) => $r->amount - $r->paid_amount;
        $due = $open->filter(fn (Receivable $r) => $r->due_date === null || $r->due_date->lte($today));

        return [
            'open' => $open,
            'balance' => (int) $open->sum($remaining),
            'due' => (int) $due->sum($remaining),
            'due_date' => $open->pluck('due_date')->filter()->min(),
        ];
    }

    public function send(Customer $customer): bool
    {
        $tenant = $this->context->get();
        $today = now($tenant->timezone)->startOfDay();
        $summary = $this->summary($customer, $today);

        if ($summary['balance'] <= 0 || ! $customer->phone) {
            return false;
        }

        $message = $this->whatsapp->sendTemplate('kasbon_reminder', $customer->phone, [
            'nama' => $customer->name,
            'sisa' => Rupiah::format($summary['balance']),
            'tagihan' => Rupiah::format($summary['due'] ?: $summary['balance']),
            'jatuh_tempo' => $this->formatDate($summary['due_date']) ?? '-',
            'tanggal' => $this->formatDate($today),
            'jumlah_nota' => (string) $summary['open']->count(),
            'link' => $this->billUrl($customer),
        ], $customer);

        if (! $message) {
            return false;
        }

        Receivable::query()->where('customer_id', $customer->id)->where('status', 'open')
            ->where(fn ($q) => $q->whereNull('due_date')->orWhereDate('due_date', '<=', $today->toDateString()))
            ->update(['last_reminded_at' => now()]);

        return true;
    }

    public function sendDue(Tenant $tenant): int
    {
        $now = now($tenant->timezone);
        if (! self::settings($tenant)['auto'] || $now->hour < self::SEND_HOURS[0] || $now->hour >= self::SEND_HOURS[1]) {
            return 0;
        }

        $customerIds = Receivable::query()->where('status', 'open')
            ->whereNotNull('due_date')->whereDate('due_date', '<=', $now->toDateString())
            ->where(fn ($q) => $q->whereNull('last_reminded_at')->orWhere('last_reminded_at', '<=', now()->subDays(self::REPEAT_DAYS)))
            ->distinct()->pluck('customer_id');

        return Customer::query()->whereIn('id', $customerIds)->whereNotNull('phone')->get()
            ->filter(fn (Customer $customer) => $this->send($customer))
            ->count();
    }

    public function submitProof(Customer $customer, int $amount, UploadedFile $file, ?string $note, ?string $ip): ReceivableProof
    {
        $tenant = $this->context->get();

        if (ReceivableProof::query()->where('customer_id', $customer->id)->where('status', 'pending')->count() >= self::MAX_PENDING) {
            throw ValidationException::withMessages(['file' => 'Bukti pembayaran sebelumnya masih dicek. Mohon tunggu konfirmasi dulu.']);
        }

        $balance = $customer->outstandingBalance();
        if ($balance <= 0) {
            throw ValidationException::withMessages(['amount' => 'Tagihan ini sudah lunas. Terima kasih 🙏']);
        }
        if ($amount > $balance) {
            throw ValidationException::withMessages(['amount' => 'Jumlah melebihi sisa tagihan '.Rupiah::format($balance).'.']);
        }

        $uuid = (string) Str::uuid();
        [$contents, $mime, $extension] = $this->prepareFile($file);
        $path = "kasbon-proofs/{$tenant->uuid}/{$uuid}.{$extension}";
        Storage::disk(self::DISK)->put($path, $contents);

        try {
            $proof = ReceivableProof::create([
                'customer_id' => $customer->id,
                'uuid' => $uuid,
                'amount' => $amount,
                'note' => $note ? Str::limit(trim($note), 300, '') : null,
                'file_path' => $path,
                'file_mime' => $mime,
                'status' => 'pending',
                'ip_address' => $ip,
            ]);
        } catch (Throwable $e) {
            Storage::disk(self::DISK)->delete($path);
            throw $e;
        }

        if ($to = $this->owner->recipient($tenant)) {
            $this->whatsapp->sendTemplate('kasbon_proof', $to, [
                'nama' => $customer->name,
                'jumlah' => Rupiah::format($amount),
                'sisa' => Rupiah::format($balance),
                'link' => route('receivables.show', $customer),
            ], $proof);
        }

        return $proof;
    }

    public function approve(ReceivableProof $proof, User $user, int $amount, ?int $paymentMethodId): int
    {
        $remaining = DB::transaction(function () use ($proof, $user, $amount, $paymentMethodId) {
            $locked = ReceivableProof::query()->whereKey($proof->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['proof' => 'Bukti ini sudah diproses.']);
            }

            $remaining = $this->receivables->pay($locked->customer, $amount, $paymentMethodId, $user, null, $locked->uuid);

            $locked->update(['status' => 'approved', 'approved_amount' => $amount, 'reviewed_by' => $user->id, 'reviewed_at' => now()]);
            $proof->setRawAttributes($locked->getAttributes(), true);

            return $remaining;
        });

        if ($proof->customer->phone) {
            $this->whatsapp->sendTemplate('kasbon_paid', $proof->customer->phone, [
                'nama' => $proof->customer->name,
                'jumlah' => Rupiah::format($amount),
                'sisa' => $remaining > 0 ? Rupiah::format($remaining) : 'LUNAS',
            ], $proof);
        }

        return $remaining;
    }

    public function reject(ReceivableProof $proof, User $user, string $reason): void
    {
        DB::transaction(function () use ($proof, $user, $reason) {
            $locked = ReceivableProof::query()->whereKey($proof->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['proof' => 'Bukti ini sudah diproses.']);
            }

            $locked->update(['status' => 'rejected', 'reject_reason' => $reason, 'reviewed_by' => $user->id, 'reviewed_at' => now()]);
            $proof->setRawAttributes($locked->getAttributes(), true);
        });

        if ($proof->customer->phone) {
            $this->whatsapp->sendTemplate('kasbon_rejected', $proof->customer->phone, [
                'nama' => $proof->customer->name,
                'jumlah' => Rupiah::format($proof->amount),
                'alasan' => $reason,
                'link' => $this->billUrl($proof->customer),
            ], $proof);
        }
    }

    public function pending(?Customer $customer = null): Collection
    {
        return ReceivableProof::query()->where('status', 'pending')
            ->when($customer, fn ($q) => $q->where('customer_id', $customer->id))
            ->with('customer:id,name')->orderBy('created_at')->limit(50)->get();
    }

    private function prepareFile(UploadedFile $file): array
    {
        $contents = (string) file_get_contents($file->getRealPath());

        if (str_starts_with($contents, '%PDF-')) {
            return [$contents, 'application/pdf', 'pdf'];
        }

        $size = @getimagesizefromstring($contents);
        if (! $size || $size[0] * $size[1] > 40_000_000) {
            throw ValidationException::withMessages(['file' => 'File tidak bisa dibaca. Kirim foto (JPG/PNG) atau PDF.']);
        }

        $image = @imagecreatefromstring($contents);
        if (! $image) {
            throw ValidationException::withMessages(['file' => 'File tidak bisa dibaca. Kirim foto (JPG/PNG) atau PDF.']);
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, self::MAX_IMAGE / max($width, $height));
        if ($scale < 1) {
            $resized = imagescale($image, (int) round($width * $scale), (int) round($height * $scale));
            imagedestroy($image);
            $image = $resized;
        }

        ob_start();
        imagejpeg($image, null, 82);
        imagedestroy($image);

        return [(string) ob_get_clean(), 'image/jpeg', 'jpg'];
    }

    private function formatDate(?Carbon $date): ?string
    {
        return $date?->locale('id')->translatedFormat('j F Y');
    }
}
