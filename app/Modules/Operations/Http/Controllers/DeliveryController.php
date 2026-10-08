<?php

namespace App\Modules\Operations\Http\Controllers;

use App\Core\Support\DocumentPaper;
use App\Core\Support\Phone;
use App\Core\Support\Qty;
use App\Core\Tenancy\CurrentOutlet;
use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Modules\Operations\Models\Delivery;
use App\Modules\Operations\Services\DeliveryService;
use App\Modules\Pos\Models\Sale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

/** Antar barang: daftar pengiriman, buat dari transaksi, ubah status, cetak surat jalan A4. */
class DeliveryController extends Controller
{
    public const STATUSES = ['pending' => 'Belum berangkat', 'on_the_way' => 'Dalam perjalanan', 'delivered' => 'Sudah sampai', 'failed' => 'Gagal / kembali'];

    public function __construct(private TenantContext $context) {}

    public function index(Request $request, CurrentOutlet $outlet): Response
    {
        $timezone = $this->context->get()->timezone;
        $status = $request->string('status')->toString();

        return Inertia::render('Operations/Deliveries', [
            'deliveries' => Delivery::query()
                ->where('outlet_id', $outlet->id($request->user()))
                ->when($status, fn ($q) => $q->where('status', $status), fn ($q) => $q->whereIn('status', ['pending', 'on_the_way']))
                ->with(['sale:id,uuid,number', 'items'])
                ->orderByRaw('scheduled_at is null, scheduled_at')->latest('id')
                ->limit(150)->get()
                ->map(fn (Delivery $d) => [
                    'uuid' => $d->uuid, 'number' => $d->number, 'sale_number' => $d->sale?->number, 'sale_uuid' => $d->sale?->uuid,
                    'recipient_name' => $d->recipient_name, 'phone' => Phone::display($d->phone), 'address' => $d->address,
                    'scheduled' => $d->scheduled_at?->timezone($timezone)->locale('id')->translatedFormat('D, j M H:i'),
                    'driver_name' => $d->driver_name, 'status' => $d->status,
                    'items' => $d->items->map(fn ($i) => Qty::display($i->qty).' '.($i->unit_name ? $i->unit_name.' ' : '').$i->name)->join(', '),
                ]),
            'statuses' => self::STATUSES,
            'filter' => $status,
        ]);
    }

    /** Formulir pengiriman dari sebuah transaksi. */
    public function create(string $uuid): Response
    {
        $sale = Sale::query()->where('uuid', $uuid)->with(['items', 'customer', 'deliveries.items'])->firstOrFail();
        $sent = [];
        foreach ($sale->deliveries->where('status', '!=', 'failed') as $delivery) {
            foreach ($delivery->items as $item) {
                $sent[$item->sale_item_id] = Qty::add($sent[$item->sale_item_id] ?? '0', $item->qty);
            }
        }

        return Inertia::render('Operations/DeliveryForm', [
            'sale' => ['uuid' => $sale->uuid, 'number' => $sale->number],
            'customer' => ['name' => $sale->customer?->name, 'phone' => $sale->customer?->phone, 'address' => $sale->customer?->address],
            'items' => $sale->items->map(function ($i) use ($sent) {
                $remaining = Qty::sub($i->qty, $sent[$i->id] ?? '0');

                return [
                    'id' => $i->id, 'name' => $i->name, 'unit' => $i->unit_name, 'qty' => Qty::display($i->qty),
                    'remaining' => Qty::display(Qty::isNegative($remaining) ? '0' : $remaining),
                ];
            }),
        ]);
    }

    public function store(Request $request, string $uuid, DeliveryService $service): RedirectResponse
    {
        $sale = Sale::query()->where('uuid', $uuid)->with('items')->firstOrFail();
        $data = $request->validate([
            'type' => ['required', Rule::in(['delivery', 'pickup'])],
            'recipient_name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['required', 'string', 'max:255'],
            'scheduled_at' => ['nullable', 'date'],
            'driver_name' => ['nullable', 'string', 'max:100'],
            'vehicle' => ['nullable', 'string', 'max:50'],
            'fee' => ['nullable', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
            'items' => ['array'],
            'items.*.sale_item_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'numeric', 'min:0'],
        ], ['recipient_name.required' => 'Nama penerima belum diisi.', 'address.required' => 'Alamat tujuan belum diisi.']);

        if (! empty($data['scheduled_at'])) {
            $data['scheduled_at'] = \Illuminate\Support\Carbon::parse($data['scheduled_at'], $this->context->get()->timezone)->utc();
        }

        $delivery = $service->create($sale, $data);

        return redirect()->route('deliveries.index')->with('success', "Surat jalan {$delivery->number} sudah dibuat.")
            ->with('openUrl', route('deliveries.print', $delivery->uuid));
    }

    public function update(Request $request, string $uuid, DeliveryService $service): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(self::STATUSES))]]);
        $service->setStatus(Delivery::query()->where('uuid', $uuid)->firstOrFail(), $data['status']);

        return back()->with('success', 'Status pengiriman: '.self::STATUSES[$data['status']].'.');
    }

    /** Surat jalan A4 (dicetak dari browser). */
    public function print(Request $request, string $uuid): View
    {
        $delivery = Delivery::query()->where('uuid', $uuid)->with(['items', 'sale', 'outlet'])->firstOrFail();

        return view('documents.delivery', [
            'delivery' => $delivery,
            'tenant' => $this->context->get(),
            'outlet' => $delivery->outlet,
            'timezone' => $this->context->get()->timezone,
            'paper' => DocumentPaper::resolve($request, $delivery->outlet),
        ]);
    }
}
