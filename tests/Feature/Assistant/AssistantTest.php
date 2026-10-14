<?php

use App\Modules\Assistant\Jobs\AnswerAssistantWhatsApp;
use App\Modules\Assistant\Models\AssistantMessage;
use App\Modules\Assistant\Models\AssistantPhone;
use App\Modules\Assistant\Models\AssistantTopup;
use App\Modules\Assistant\Services\Agent;
use App\Modules\Assistant\Services\Llm;
use App\Modules\Assistant\Services\LlmReply;
use App\Modules\Assistant\Services\Quota;
use App\Modules\Assistant\Services\StoreTools;
use App\Modules\Customer\Models\Customer;
use App\Modules\Operations\Models\Receivable;
use App\Modules\Pos\Models\Sale;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/** AI palsu: pertama memanggil alat, lalu menjawab dengan angka dari hasil alat. Tanpa biaya. */
class ScriptedLlm implements Llm
{
    public array $calls = [];

    public function __construct(private string $tool = 'ringkasan_penjualan', private array $input = []) {}

    public function available(): bool
    {
        return true;
    }

    public function send(string $model, string $effort, int $maxTokens, array $system, array $tools, array $messages): LlmReply
    {
        $this->calls[] = compact('model', 'effort', 'maxTokens', 'system', 'tools', 'messages');
        $last = end($messages)['content'];
        $usage = ['input' => 2000, 'output' => 300, 'cache_read' => 0, 'cache_write' => 0];

        if (is_array($last) && ($last[0]['type'] ?? null) === 'tool_result') {
            $data = json_decode($last[0]['content'], true);

            return new LlmReply('end_turn', '**Uang masuk** hari ini Rp'.number_format($data['uang_masuk'] ?? 0, 0, ',', '.'), [], [], $usage, $model);
        }

        $call = ['id' => 'toolu_1', 'name' => $this->tool, 'input' => $this->input];

        return new LlmReply('tool_use', '', [$call], [['type' => 'tool_use'] + $call], $usage, $model);
    }
}

beforeEach(function () {
    $this->owner = registerTenant('toko_kelontong');
    $this->tenant = $this->owner->tenant;
    $this->llm = new ScriptedLlm;
    app()->instance(Llm::class, $this->llm);
    config(['hermes.assistant.webhook_secret' => 'rahasia-asisten-123456']);
});

function sale(int $total, $owner): void
{
    asTenant($owner->tenant, fn () => Sale::create([
        'outlet_id' => outletsOf($owner)->first()->id,
        'uuid' => (string) Str::uuid(),
        'number' => 'S-'.Str::random(6),
        'cashier_id' => $owner->id,
        'status' => 'completed',
        'subtotal' => $total,
        'total' => $total,
        'completed_at' => now(),
    ]));
}

describe('Asisten Toko', function () {
    it('menjawab dengan angka dari laporan dan mencatat biaya pulsa', function () {
        sale(150000, $this->owner);
        sale(50000, $this->owner);

        $res = $this->actingAs($this->owner)->postJson(route('assistant.ask'), ['question' => 'Penjualan hari ini berapa?'])
            ->assertOk()->assertJson(['status' => 'ok', 'mode' => 'quick', 'tools' => ['ringkasan_penjualan']]);

        expect($res->json('text'))->toBe('*Uang masuk* hari ini Rp200.000')
            ->and($res->json('cost'))->toBeGreaterThan(0)
            ->and($this->llm->calls[0]['model'])->toBe('claude-haiku-5-5')
            ->and($this->llm->calls[0]['system'][0]['cacheControl'])->toBe(['type' => 'ephemeral']);

        expect(AssistantMessage::allTenants()->where('role', 'assistant')->sole())
            ->tenant_id->toBe($this->tenant->id)
            ->status->toBe('ok');
    });

    it('alat tidak pernah melihat data usaha lain', function () {
        $other = registerTenant('toko_kelontong', '6281299990000');
        sale(999000, $other);
        sale(10000, $this->owner);

        $data = asTenant($this->tenant, fn () => app(StoreTools::class)->run('ringkasan_penjualan', []));
        expect($data['uang_masuk'])->toBe(10000);

        asTenant($other->tenant, function () {
            $c = Customer::create(['uuid' => (string) Str::uuid(), 'name' => 'Pelanggan Rahasia']);
            Receivable::create(['customer_id' => $c->id, 'type' => 'kasbon', 'amount' => 500000, 'paid_amount' => 0, 'status' => 'open']);
        });
        $piutang = asTenant($this->tenant, fn () => app(StoreTools::class)->run('piutang', []));
        expect($piutang['total_piutang'])->toBe(0)->and(json_encode($piutang))->not->toContain('Rahasia');
    });

    it('pertanyaan "kenapa" memakai mode analisis', function () {
        $this->actingAs($this->owner)->postJson(route('assistant.ask'), ['question' => 'Kenapa penjualan minggu ini turun?'])
            ->assertOk()->assertJson(['mode' => 'deep']);

        expect($this->llm->calls[0]['effort'])->toBe('high');
    });

    it('paket di bawah Bisnis tidak memakai AI', function () {
        $this->tenant->forceFill(['status' => 'active', 'plan' => 'pro', 'paid_until' => now()->addMonth()->toDateString()])->save();

        $this->actingAs($this->owner)->postJson(route('assistant.ask'), ['question' => 'Penjualan hari ini?'])
            ->assertOk()->assertJson(['status' => 'plan']);

        expect($this->llm->calls)->toBe([]);
    });

    it('pulsa habis dijawab tanpa AI, dan tambah pulsa membukanya lagi', function () {
        asTenant($this->tenant, fn () => AssistantMessage::create(['channel' => 'web', 'role' => 'assistant', 'content' => 'x', 'cost' => 99999]));

        $this->actingAs($this->owner)->postJson(route('assistant.ask'), ['question' => 'Penjualan hari ini?'])
            ->assertOk()->assertJson(['status' => 'quota']);
        expect($this->llm->calls)->toBe([]);

        $this->artisan('pos:asisten', ['tenant' => $this->tenant->id, '--tambah' => 200000])->expectsConfirmation("Tambah pulsa Rp200000 untuk [{$this->tenant->name}]?", 'yes')->assertSuccessful();
        expect(AssistantTopup::allTenants()->where('tenant_id', $this->tenant->id)->sum('amount'))->toEqual(200000);
        expect(Quota::for($this->tenant)->check('quick'))->toBe('day');
    });

    it('kasir tidak bisa membuka asisten', function () {
        $kasir = addStaff($this->owner, 'kasir');

        $this->actingAs($kasir)->get(route('assistant.index'))->assertForbidden();
        $this->actingAs($kasir)->postJson(route('assistant.ask'), ['question' => 'laba?'])->assertForbidden();
    });

    it('satu nomor WA hanya untuk satu usaha', function () {
        $this->actingAs($this->owner)->post(route('assistant.phones.store'), ['phone' => '0812 3333 4444'])->assertSessionHasNoErrors();

        $other = registerTenant('toko_kelontong', '6281277770000');
        $this->actingAs($other)->post(route('assistant.phones.store'), ['phone' => '6281233334444'])->assertSessionHasErrors('phone');
    });
});

describe('webhook WhatsApp Asisten Toko', function () {
    beforeEach(function () {
        asTenant($this->tenant, fn () => AssistantPhone::create(['phone' => '6281233334444', 'user_id' => $this->owner->id]));
        Queue::fake();
    });

    it('menolak tanpa kode rahasia yang benar', function () {
        $this->post('/webhook/asisten/salah-kode-rahasia-1234', ['sender' => '6281233334444', 'message' => 'halo'])->assertNotFound();
        Queue::assertNothingPushed();
    });

    it('mengabaikan nomor asing, grup, dan pesan dobel', function () {
        $url = '/webhook/asisten/rahasia-asisten-123456';

        $this->post($url, ['sender' => '6281200001111', 'message' => 'omzet?'])->assertJson(['reason' => 'unknown']);
        $this->post($url, ['sender' => '6281233334444', 'message' => 'omzet?', 'member' => '628111'])->assertJson(['reason' => 'group']);
        Queue::assertNothingPushed();

        $this->post($url, ['sender' => '6281233334444', 'message' => 'omzet?', 'inboxid' => 'abc'])->assertJson(['status' => 'accepted']);
        Queue::assertPushed(AnswerAssistantWhatsApp::class, 1);
    });

    it('job menjawab ke nomor pengirim memakai data usahanya sendiri', function () {
        sale(75000, $this->owner);
        config(['hermes.assistant.fonnte_token' => 'token-asisten']);
        Http::fake(['api.fonnte.com/*' => Http::response(['status' => true, 'id' => ['1']])]);

        $phone = AssistantPhone::allTenants()->sole();
        (new AnswerAssistantWhatsApp($phone->id, 'penjualan hari ini?', 'fonnte:xyz'))->handle(app(Agent::class), app(\App\Modules\WhatsApp\Services\WhatsAppManager::class));

        Http::assertSent(fn ($r) => $r['target'] === '6281233334444' && str_contains($r['message'], 'Rp75.000') && $r->header('Authorization')[0] === 'token-asisten');

        (new AnswerAssistantWhatsApp($phone->id, 'penjualan hari ini?', 'fonnte:xyz'))->handle(app(Agent::class), app(\App\Modules\WhatsApp\Services\WhatsAppManager::class));
        Http::assertSentCount(1);
    });
});

it('semua alat bisa dijalankan dan outlet asing ditolak', function () {
    sale(20000, $this->owner);
    $tools = asTenant($this->tenant, fn () => app(StoreTools::class));

    foreach ($tools->definitions() as $def) {
        $data = asTenant($this->tenant, fn () => $tools->run($def['name'], ['dari' => now()->subDays(40)->toDateString()]));
        expect($data)->toBeArray();
    }

    expect(fn () => asTenant($this->tenant, fn () => $tools->run('ringkasan_penjualan', ['outlet' => 'Cabang Tidak Ada'])))
        ->toThrow(InvalidArgumentException::class);
});

it('memberi peringatan sekali saat pulsa terpakai 80%', function () {
    config(['hermes.assistant.trial_budget' => 1000, 'hermes.assistant.budget' => 1000]);
    asTenant($this->tenant, fn () => AssistantMessage::create(['channel' => 'web', 'role' => 'assistant', 'content' => 'x', 'cost' => 790]));

    $first = $this->actingAs($this->owner)->postJson(route('assistant.ask'), ['question' => 'Penjualan hari ini?'])->json('text');
    $second = $this->actingAs($this->owner)->postJson(route('assistant.ask'), ['question' => 'Penjualan hari ini?'])->json('text');

    expect($first)->toContain('Pulsa Asisten tinggal 19%')
        ->and($second)->not->toContain('Pulsa Asisten tinggal');
});
