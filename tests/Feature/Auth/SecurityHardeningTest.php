<?php

require_once __DIR__.'/../Pos/PosTestHelpers.php';
require_once __DIR__.'/../Modules/ModuleTestHelpers.php';

use App\Models\Device;
use App\Modules\Auth\Services\DeviceService;
use App\Modules\WhatsApp\Drivers\WablasDriver;
use App\Modules\WhatsApp\Models\WhatsappMessage;
use App\Modules\WhatsApp\Services\WhatsAppService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

function deviceOf($test, $owner): string
{
    $test->post(route('login.store'), ['phone' => $owner->phone, 'password' => 'rahasia123']);
    $uuid = Device::allTenants()->where('tenant_id', $owner->tenant_id)->value('uuid');
    $test->post(route('logout'));

    return $uuid;
}

it('hanya menerima domain Wablas resmi', function (string $domain, bool $ok) {
    expect(WablasDriver::isAllowedDomain($domain))->toBe($ok);
})->with([
    ['https://solo.wablas.com', true],
    ['https://wablas.com/', true],
    ['http://solo.wablas.com', false],
    ['https://169.254.169.254', false],
    ['https://127.0.0.1', false],
    ['https://solo.wablas.com.evil.example', false],
    ['https://evil-wablas.com', false],
    ['https://solo.wablas.com:8080', false],
    ['https://user@solo.wablas.com', false],
    ['https://solo.wablas.com/redirect?to=x', false],
]);

it('tidak menghubungi alamat internal dan tidak menampilkan isi balasannya', function () {
    Http::fake(['*' => Http::response(['status' => false, 'message' => 'INTERNAL-SECRET'], 200)]);

    $result = (new WablasDriver('t', 'http://169.254.169.254'))->send('6281211112222', 'Halo');

    expect($result->ok)->toBeFalse()->and($result->error)->not->toContain('INTERNAL-SECRET');
    Http::assertNothingSent();
});

it('menolak menyimpan domain Wablas yang bukan resmi', function () {
    $owner = registerTenant('barbershop');

    $this->actingAs($owner)->put(route('settings.whatsapp.account'), [
        'mode' => 'own', 'provider' => 'wablas', 'token' => 'abc', 'domain' => 'http://127.0.0.1:8080',
    ])->assertSessionHasErrors('domain');

    $this->actingAs($owner)->put(route('settings.whatsapp.account'), [
        'mode' => 'own', 'provider' => 'wablas', 'token' => 'abc', 'domain' => 'https://solo.wablas.com',
    ])->assertSessionHasNoErrors();
});

it('mengunci PIN setelah 20 kali salah dalam sehari, mengabari pemilik, dan terbuka lagi saat PIN diganti', function () {
    $owner = registerTenant();
    $kasir = addStaff($owner, 'kasir', '4321');
    $uuid = deviceOf($this, $owner);

    foreach (range(1, 20) as $i) {
        RateLimiter::clear('pin:'.Device::allTenants()->where('uuid', $uuid)->value('id').'|'.$kasir->id);
        $this->withCookie(DeviceService::COOKIE, $uuid)->post(route('pin.store'), ['user_id' => $kasir->id, 'pin' => '0000']);
    }

    $this->withCookie(DeviceService::COOKIE, $uuid)->post(route('pin.store'), ['user_id' => $kasir->id, 'pin' => '4321'])
        ->assertSessionHasErrors(['pin' => 'PIN akun ini dikunci karena terlalu sering salah. Minta pemilik usaha mengganti PIN Anda di menu Karyawan.']);
    $this->assertGuest();

    $warning = asTenant($owner->tenant, fn () => WhatsappMessage::query()->where('body', 'like', '%Peringatan keamanan%')->get());
    expect($warning)->toHaveCount(1)->and($warning[0]->body)->toContain($kasir->name);

    asTenant($owner->tenant, function () use ($kasir) {
        $kasir->setPin('9876');
        $kasir->save();
    });

    $this->withCookie(DeviceService::COOKIE, $uuid)->post(route('pin.store'), ['user_id' => $kasir->id, 'pin' => '9876'])
        ->assertRedirect(route('pos.show'));
});

it('mengirim header keamanan', function () {
    $this->get(route('login'))
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

it('mengalihkan http ke https di production', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->get('http://localhost/masuk?x=1')->assertRedirect('https://localhost/masuk?x=1')->assertStatus(301);
    $this->get('https://localhost/masuk')->assertOk();
});
