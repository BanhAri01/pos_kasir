<?php

use App\Models\User;
use App\Modules\WhatsApp\Drivers\FonnteDriver;
use App\Modules\WhatsApp\Models\WhatsappAccount;
use App\Modules\WhatsApp\Services\WhatsAppHealth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'hermes.whatsapp.driver' => 'fonnte',
        'hermes.whatsapp.credentials.token' => 'token-pusat',
        'hermes.assistant.fonnte_token' => 'token-asisten',
        'hermes.whatsapp.alert_email' => 'ari@contoh.test',
    ]);
    $this->sendResponses = [];
    $this->statuses = ['token-pusat' => 'connect', 'token-asisten' => 'connect', 'token-toko' => 'connect'];
    Http::fake(function ($request) {
        if (str_contains($request->url(), '/device')) {
            return Http::response(['status' => true, 'device_status' => $this->statuses[$request->header('Authorization')[0]] ?? 'connect']);
        }

        return Http::response(array_shift($this->sendResponses) ?? ['status' => true, 'id' => ['1']]);
    });
});

function sentEmails(): array
{
    return array_map(fn ($sent) => $sent->getOriginalMessage(), app('mailer')->getSymfonyTransport()->messages()->all());
}

function adminUser(): User
{
    $admin = new User;
    $admin->forceFill(['name' => 'Admin', 'phone' => '6289900002222', 'password' => Hash::make('rahasia-admin'), 'is_super_admin' => true, 'is_active' => true])->save();

    return $admin->fresh();
}

it('mendeteksi nomor pusat terputus dan mengirim email ke admin sekali', function () {
    $this->statuses['token-pusat'] = 'disconnect';

    $result = app(WhatsAppHealth::class)->check();
    app(WhatsAppHealth::class)->check();

    expect($result)->toBe(['platform' => 'terputus', 'asisten' => 'tersambung'])
        ->and(app(WhatsAppHealth::class)->down())->toHaveKey('platform')
        ->and(sentEmails())->toHaveCount(1)
        ->and(sentEmails()[0]->getSubject())->toContain('WhatsApp terputus')
        ->and(sentEmails()[0]->getTo()[0]->getAddress())->toBe('ari@contoh.test');
});

it('mengulang email tiap 6 jam lalu mengabarkan saat tersambung lagi', function () {
    $this->statuses['token-pusat'] = 'disconnect';
    app(WhatsAppHealth::class)->check();

    $this->travel(7)->hours();
    app(WhatsAppHealth::class)->check();
    expect(sentEmails())->toHaveCount(2)->and(sentEmails()[1]->getSubject())->toContain('masih terputus');

    $this->statuses['token-pusat'] = 'connect';
    app(WhatsAppHealth::class)->check();

    expect(app(WhatsAppHealth::class)->down())->toBe([])
        ->and(sentEmails())->toHaveCount(3)
        ->and(sentEmails()[2]->getSubject())->toContain('tersambung lagi');
});

it('pesan gagal "disconnected" langsung menandai terputus, pesan berhasil memulihkan', function () {
    $this->sendResponses = [['status' => false, 'reason' => 'request invalid on disconnected device']];

    expect((new FonnteDriver('token-pusat'))->send('6281200000000', 'Halo')->ok)->toBeFalse()
        ->and(app(WhatsAppHealth::class)->down())->toHaveKey('platform');

    expect((new FonnteDriver('token-pusat'))->send('6281200000000', 'Halo')->ok)->toBeTrue()
        ->and(app(WhatsAppHealth::class)->down())->toBe([]);
});

it('pemilik usaha yang memakai nomor pusat melihat peringatan, kasir tidak', function () {
    $owner = registerTenant();
    $kasir = addStaff($owner);
    $this->statuses['token-pusat'] = 'disconnect';
    app(WhatsAppHealth::class)->check();

    $this->actingAs($owner)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('waNotices.0.fix', false)->where('waNotices.0.text', fn ($t) => str_contains($t, 'WhatsApp Hermes POS sedang terputus')));
    $this->actingAs($kasir)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('waNotices', []));

    $this->actingAs(adminUser())->get(route('admin.tenants.index'))
        ->assertInertia(fn ($page) => $page->where('waNotices.0.fix', true));
});

it('nomor milik usaha yang terputus hanya diberitahukan ke pemilik usaha itu, tanpa email admin', function () {
    $owner = registerTenant();
    $owner->tenant->forceFill(['status' => 'active', 'plan' => 'bisnis', 'paid_until' => now()->addMonth()->toDateString()])->save();
    WhatsappAccount::create(['tenant_id' => $owner->tenant_id, 'provider' => 'fonnte', 'credentials' => ['token' => 'token-toko'], 'is_active' => true]);
    $other = registerTenant('toko_kelontong', '6281277770000');

    $this->statuses['token-toko'] = 'disconnect';
    $this->statuses['token-pusat'] = 'disconnect';
    app(WhatsAppHealth::class)->check();

    $notices = app(WhatsAppHealth::class)->noticesFor($owner->fresh(), $owner->tenant->fresh());
    expect($notices)->toHaveCount(1)
        ->and($notices[0]['text'])->toContain('Nomor WhatsApp usaha Anda terputus')
        ->and($notices[0]['fix'])->toBeTrue()
        ->and(app(WhatsAppHealth::class)->noticesFor($other, $other->tenant)[0]['text'])->toContain('WhatsApp Hermes POS sedang terputus')
        ->and(collect(sentEmails())->map->getSubject()->filter(fn ($s) => str_contains($s, 'terputus')))->toHaveCount(1);
});

it('mode uji tanpa Fonnte tidak memantau apa pun', function () {
    config(['hermes.whatsapp.driver' => 'log', 'hermes.assistant.fonnte_token' => null]);

    expect(app(WhatsAppHealth::class)->check())->toBe([]);
    Http::assertNothingSent();
});
