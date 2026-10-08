<?php

namespace App\Modules\Operations\Http\Controllers;

use App\Core\Tenancy\CurrentOutlet;
use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Outlet;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

class OnlineStoreController extends Controller
{
    public function __construct(private CurrentOutlet $outlet, private TenantContext $context) {}

    public function edit(Request $request): Response
    {
        $outlet = $this->current($request);
        $tenant = $this->context->get();

        return Inertia::render('Operations/OnlineStore', [
            'outlet' => [
                'name' => $outlet->name,
                'url' => route('online-store.show', $outlet->order_token),
                'online_open' => (bool) $outlet->online_open,
                'online_pickup' => (bool) $outlet->online_pickup,
                'online_delivery' => (bool) $outlet->online_delivery,
                'delivery_fee' => (int) $outlet->delivery_fee,
                'online_min_order' => (int) $outlet->online_min_order,
                'has_phone' => filled($outlet->phone),
            ],
            'paymentOnline' => $tenant->qr_payment === 'online' && filled($tenant->midtrans_server_key),
            'hasQrOrder' => $tenant->hasModule('qr_order'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'online_open' => ['boolean'],
            'online_pickup' => ['boolean'],
            'online_delivery' => ['boolean'],
            'delivery_fee' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'online_min_order' => ['nullable', 'integer', 'min:0', 'max:100000000'],
        ]);

        if (empty($data['online_pickup']) && empty($data['online_delivery'])) {
            return back()->withErrors(['online_pickup' => 'Pilih minimal satu: ambil sendiri atau diantar.']);
        }

        $this->current($request)->update([
            'online_open' => (bool) ($data['online_open'] ?? false),
            'online_pickup' => (bool) ($data['online_pickup'] ?? false),
            'online_delivery' => (bool) ($data['online_delivery'] ?? false),
            'delivery_fee' => (int) ($data['delivery_fee'] ?? 0),
            'online_min_order' => (int) ($data['online_min_order'] ?? 0),
        ]);

        return back()->with('success', 'Pengaturan toko online disimpan.');
    }

    public function poster(Request $request): View
    {
        $outlet = $this->current($request);
        $url = route('online-store.show', $outlet->order_token);
        $writer = new Writer(new ImageRenderer(new RendererStyle(360, 2), new SvgImageBackEnd));

        return view('documents.store-poster', [
            'tenant' => $this->context->get(),
            'outlet' => $outlet,
            'url' => $url,
            'svg' => preg_replace('/^<\?xml[^>]*>\s*/', '', $writer->writeString($url)),
        ]);
    }

    public function regenerate(Request $request): RedirectResponse
    {
        $this->current($request)->forceFill(['order_token' => Str::random(32)])->save();

        return back()->with('success', 'Link toko sudah diganti. Link lama tidak bisa dipakai lagi, bagikan link yang baru.');
    }

    private function current(Request $request): Outlet
    {
        $outlet = Outlet::query()->findOrFail($this->outlet->id($request->user()));
        if (! $outlet->order_token) {
            $outlet->forceFill(['order_token' => Str::random(32)])->save();
        }

        return $outlet;
    }
}
