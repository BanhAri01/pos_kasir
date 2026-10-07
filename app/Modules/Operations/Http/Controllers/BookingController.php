<?php

namespace App\Modules\Operations\Http\Controllers;

use App\Core\Support\Phone;
use App\Core\Tenancy\CurrentOutlet;
use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customer\Models\Customer;
use App\Modules\Operations\Models\Booking;
use App\Modules\Operations\Services\BookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Janji temu: daftar per hari, buat janji (cek jadwal karyawan bentrok), ubah status. */
class BookingController extends Controller
{
    public function __construct(private TenantContext $context, private CurrentOutlet $outlet) {}

    public function index(Request $request): Response
    {
        $timezone = $this->context->get()->timezone;
        $date = $request->date('tanggal') ?? now($timezone);
        $day = Carbon::parse($date->toDateString(), $timezone);

        $bookings = Booking::query()
            ->where('outlet_id', $this->outlet->id($request->user()))
            ->whereBetween('start_at', [$day->copy()->startOfDay()->utc(), $day->copy()->endOfDay()->utc()])
            ->with(['items', 'staff:id,name'])
            ->orderBy('start_at')
            ->get()
            ->map(fn (Booking $b) => [
                'id' => $b->id,
                'customer_name' => $b->customer_name,
                'customer_phone' => Phone::display($b->customer_phone),
                'phone_raw' => $b->customer_phone,
                'staff' => $b->staff?->name,
                'start' => $b->start_at->timezone($timezone)->format('H:i'),
                'end' => $b->end_at->timezone($timezone)->format('H:i'),
                'services' => $b->items->pluck('name')->join(', '),
                'total_price' => $b->total_price,
                'status' => $b->status,
                'note' => $b->note,
            ]);

        return Inertia::render('Operations/Bookings', [
            'bookings' => $bookings,
            'date' => $day->toDateString(),
            'services' => Product::query()->where('type', 'service')->where('is_active', true)->orderBy('name')->get(['id', 'name', 'price', 'duration_minutes']),
            'staff' => User::sameTenant()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'job_title']),
        ]);
    }

    public function store(Request $request, BookingService $service): RedirectResponse
    {
        $tenantId = $this->context->id();
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:100'],
            'customer_phone' => ['nullable', 'string', 'max:20'],
            'staff_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'date' => ['required', 'date'],
            'time' => ['required', 'date_format:H:i'],
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', Rule::exists('products', 'id')->where('tenant_id', $tenantId)],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'customer_name.required' => 'Nama pelanggan belum diisi.',
            'product_ids.required' => 'Pilih minimal satu layanan.',
            'time.required' => 'Pilih jamnya.',
        ]);

        $phone = Phone::normalize($data['customer_phone'] ?? null);
        $customer = $phone ? Customer::query()->where('phone', $phone)->first() : null;

        $booking = $service->create([
            ...$data,
            'outlet_id' => $this->outlet->id($request->user()),
            'customer_id' => $customer?->id,
            'start_at' => "{$data['date']} {$data['time']}",
        ], $request->user(), $this->context->get()->timezone);

        return redirect()->route('bookings.index', ['tanggal' => $data['date']])
            ->with('success', "Janji {$booking->customer_name} jam {$data['time']} sudah dicatat.");
    }

    public function update(Request $request, Booking $booking, BookingService $service): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['booked', 'arrived', 'done', 'canceled', 'no_show'])]]);
        $service->setStatus($booking, $data['status']);

        return back()->with('success', 'Status janji sudah diubah.');
    }
}
