<?php

require_once __DIR__.'/../Pos/PosTestHelpers.php';
require_once __DIR__.'/../Modules/ModuleTestHelpers.php';

use App\Modules\WhatsApp\Models\MessageTemplate;
use App\Modules\WhatsApp\Models\WhatsappMessage;
use App\Modules\WhatsApp\Services\WhatsAppService;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

it('pesan yang dimatikan pemilik tidak dikirim, dan bisa dinyalakan lagi', function () {
    $owner = registerTenant('toko_kelontong');
    $customer = makeCustomer($owner);
    $this->actingAs($owner)->post(route('receivables.store'), ['customer_id' => $customer->id, 'amount' => 50000]);

    $this->actingAs($owner)->put(route('settings.whatsapp.toggle', 'kasbon_reminder'), ['enabled' => false])->assertSessionHas('success');
    $this->actingAs($owner)->post(route('receivables.remind', $customer))->assertSessionHas('error');
    $sent = asTenant($owner->tenant, fn () => app(WhatsAppService::class)->sendTemplate('kasbon_reminder', '6281311112222', ['nama' => 'x']));
    expect($sent)->toBeNull()
        ->and(asTenant($owner->tenant, fn () => WhatsappMessage::count()))->toBe(0);

    $this->actingAs($owner)->put(route('settings.whatsapp.toggle', 'kasbon_reminder'), ['enabled' => true]);
    $this->actingAs($owner)->post(route('receivables.remind', $customer))->assertSessionHas('success');
    expect(asTenant($owner->tenant, fn () => WhatsappMessage::where('template_code', 'kasbon_reminder')->count()))->toBe(1);
});

it('mematikan satu pesan tidak mengubah isi pesannya dan tidak memengaruhi usaha lain', function () {
    $owner = registerTenant('barbershop');
    $other = registerTenant('barbershop', null, 'Barber Lain');

    $this->actingAs($owner)->put(route('settings.whatsapp.toggle', 'receipt'), ['enabled' => false]);

    expect(asTenant($owner->tenant, fn () => MessageTemplate::count()))->toBe(0)
        ->and(WhatsAppService::isEnabled($owner->tenant->fresh(), 'receipt'))->toBeFalse()
        ->and(WhatsAppService::isEnabled($owner->tenant->fresh(), 'booking_reminder'))->toBeTrue()
        ->and(WhatsAppService::isEnabled($other->tenant->fresh(), 'receipt'))->toBeTrue();

    $this->actingAs($owner)->put(route('settings.whatsapp.toggle', 'tidak_ada'), ['enabled' => false])->assertNotFound();
});

it('hanya menampilkan pesan yang sesuai fitur usaha, dan kasir tidak bisa mengubah', function () {
    $owner = registerTenant('toko_kelontong');

    $this->actingAs($owner)->get(route('settings.whatsapp'))->assertInertia(fn (Assert $page) => $page
        ->where('templates', fn ($t) => collect($t)->pluck('code')->contains('kasbon_reminder')
            && collect($t)->pluck('code')->contains('daily_report')
            && ! collect($t)->pluck('code')->contains('booking_reminder')));

    $kasir = addStaff($owner, 'kasir');
    $this->actingAs($kasir)->put(route('settings.whatsapp.toggle', 'receipt'), ['enabled' => false])->assertForbidden();
});

it('migrasi memindahkan tombol mati lama ke pengaturan baru', function () {
    $owner = registerTenant('barbershop');
    asTenant($owner->tenant, fn () => MessageTemplate::create(['code' => 'receipt', 'body' => WhatsAppService::DEFAULT_TEMPLATES['receipt'], 'is_active' => false]));
    asTenant($owner->tenant, fn () => MessageTemplate::create(['code' => 'booking_reminder', 'body' => 'Custom {nama}', 'is_active' => false]));

    (require base_path('app/Modules/Operations/Database/Migrations/2026_10_10_000002_move_whatsapp_toggles_to_settings.php'))->up();

    $tenant = $owner->tenant->fresh();
    expect(WhatsAppService::isEnabled($tenant, 'receipt'))->toBeFalse()
        ->and(WhatsAppService::isEnabled($tenant, 'booking_reminder'))->toBeFalse()
        ->and(DB::table('message_templates')->where('tenant_id', $tenant->id)->pluck('code')->all())->toBe(['booking_reminder'])
        ->and(DB::table('message_templates')->where('tenant_id', $tenant->id)->value('is_active'))->toBeTruthy();
});
