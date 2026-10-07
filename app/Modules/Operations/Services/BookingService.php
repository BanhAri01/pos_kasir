<?php

namespace App\Modules\Operations\Services;

use App\Core\Support\Phone;
use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Operations\Models\Booking;
use App\Modules\WhatsApp\Services\WhatsAppService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Janji temu: lama layanan dihitung dari daftar layanan, dan jadwal karyawan tidak boleh bentrok.
 */
class BookingService
{
    public function __construct(private WhatsAppService $whatsapp) {}

    /**
     * @param  array{outlet_id:int, customer_id?:int|null, customer_name:string, customer_phone?:string|null,
     *               staff_id?:int|null, start_at:string, product_ids:list<int>, note?:string|null}  $data
     */
    public function create(array $data, User $user, string $timezone): Booking
    {
        return DB::transaction(function () use ($data, $user, $timezone) {
            $services = Product::query()->whereIn('id', $data['product_ids'])->get();
            $minutes = max(15, (int) $services->sum(fn ($p) => $p->duration_minutes ?: 30));

            $start = Carbon::parse($data['start_at'], $timezone)->utc();
            $end = $start->copy()->addMinutes($minutes);

            if (! empty($data['staff_id'])) {
                $this->ensureFree((int) $data['staff_id'], $start, $end, null, $timezone);
            }

            $booking = Booking::create([
                'outlet_id' => $data['outlet_id'],
                'uuid' => (string) Str::uuid(),
                'customer_id' => $data['customer_id'] ?? null,
                'customer_name' => $data['customer_name'],
                'customer_phone' => Phone::normalize($data['customer_phone'] ?? null),
                'staff_id' => $data['staff_id'] ?? null,
                'start_at' => $start,
                'end_at' => $end,
                'status' => 'booked',
                'note' => $data['note'] ?? null,
                'total_price' => (int) $services->sum('price'),
                'created_by' => $user->id,
            ]);

            foreach ($services as $service) {
                $booking->items()->create([
                    'product_id' => $service->id,
                    'name' => $service->name,
                    'duration_minutes' => $service->duration_minutes ?: 30,
                    'price' => $service->price,
                ]);
            }

            return $booking;
        });
    }

    public function ensureFree(int $staffId, Carbon $start, Carbon $end, ?int $ignoreId, string $timezone): void
    {
        $clash = Booking::query()
            ->where('staff_id', $staffId)
            ->whereIn('status', ['booked', 'arrived'])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('start_at', '<', $end)
            ->where('end_at', '>', $start)
            ->with('staff:id,name')
            ->first();

        if ($clash) {
            $from = $clash->start_at->timezone($timezone)->format('H:i');
            $to = $clash->end_at->timezone($timezone)->format('H:i');

            throw ValidationException::withMessages([
                'start_at' => "{$clash->staff?->name} sudah ada janji jam {$from}-{$to} ({$clash->customer_name}). Pilih jam atau karyawan lain.",
            ]);
        }
    }

    public function setStatus(Booking $booking, string $status): Booking
    {
        $booking->update(['status' => $status]);

        return $booking;
    }

    /** Pengingat WhatsApp untuk janji yang dimulai dalam 2 jam ke depan (dipanggil penjadwal). */
    public function sendDueReminders(string $timezone): int
    {
        $count = 0;
        Booking::query()->where('status', 'booked')->whereNull('reminder_sent_at')->whereNotNull('customer_phone')
            ->whereBetween('start_at', [now(), now()->addHours(2)])->with('items')->get()
            ->each(function (Booking $booking) use ($timezone, &$count) {
                $this->whatsapp->sendTemplate('booking_reminder', $booking->customer_phone, [
                    'nama' => $booking->customer_name,
                    'jam' => $booking->start_at->timezone($timezone)->format('H:i'),
                    'layanan' => $booking->items->pluck('name')->join(', '),
                ], $booking);
                $booking->update(['reminder_sent_at' => now()]);
                $count++;
            });

        return $count;
    }
}
