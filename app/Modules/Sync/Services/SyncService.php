<?php

namespace App\Modules\Sync\Services;

use App\Core\Support\Phone;
use App\Core\Support\Qty;
use App\Core\Tenancy\TenantContext;
use App\Models\Device;
use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Services\CustomerService;
use App\Modules\Operations\Services\KitchenService;
use App\Modules\Operations\Services\ReceivableService;
use App\Modules\Pos\Support\SaleRules;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Pos\Models\Sale;
use App\Modules\Pos\Models\Shift;
use App\Modules\Pos\Services\SaleService;
use App\Modules\Pos\Services\ShiftService;
use App\Modules\Sync\Models\SyncBatch;
use App\Modules\Sync\Models\SyncConflict;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Memproses antrean dari kasir offline, berurutan sesuai urutan di HP.
 *
 * Prinsip:
 *  - IDEMPOTENT: setiap data membawa uuid dari HP. Dikirim berkali-kali tetap tercatat sekali.
 *  - TIDAK PERNAH MENGHILANGKAN DATA: penjualan yang sudah terjadi tidak ditolak.
 *    Kalau memang tidak bisa diproses (data rusak), disimpan di sync_conflicts untuk diperiksa pemilik,
 *    dan HP boleh menghapusnya dari antrean.
 *  - Kesalahan sementara (server error) dikembalikan sebagai "error" supaya HP mencoba lagi nanti.
 *
 * Status hasil per item: ok | conflict | error
 */
class SyncService
{
    public const TYPES = ['shift.open', 'customer.create', 'sale', 'cash', 'kitchen.ticket', 'receivable.payment'];

    public function __construct(
        private SaleService $sales,
        private ShiftService $shifts,
        private CustomerService $customers,
        private KitchenService $kitchen,
        private ReceivableService $receivables,
        private TenantContext $context,
    ) {}

    /**
     * @param  list<array{id: int|string, type: string, payload: array}>  $items
     * @return list<array{id: int|string, status: string, message?: string, data?: array}>
     */
    public function process(array $items, User $user, ?Device $device): array
    {
        $results = [];

        foreach ($items as $item) {
            try {
                $results[] = ['id' => $item['id'], 'status' => 'ok', 'data' => $this->handle($item['type'], $item['payload'] ?? [], $user, $device)];
            } catch (ValidationException $e) {
                // Data tidak valid: tidak akan berhasil walau dicoba lagi. Simpan untuk pemilik.
                $message = collect($e->errors())->flatten()->first() ?? 'Data tidak bisa diproses.';
                $this->conflict($device, 'rejected', $item, $message);
                $results[] = ['id' => $item['id'], 'status' => 'conflict', 'message' => $message];
            } catch (Throwable $e) {
                Log::error('Sinkronisasi gagal', ['type' => $item['type'] ?? null, 'error' => $e->getMessage()]);
                $results[] = ['id' => $item['id'], 'status' => 'error', 'message' => 'Gagal sementara, akan dicoba lagi.'];
            }
        }

        SyncBatch::create([
            'device_id' => $device?->id,
            'user_id' => $user->id,
            'item_count' => count($items),
            'ok_count' => collect($results)->where('status', 'ok')->count(),
            'conflict_count' => collect($results)->where('status', 'conflict')->count(),
            'error_count' => collect($results)->where('status', 'error')->count(),
        ]);

        $device?->forceFill(['last_synced_at' => now()])->save();

        return $results;
    }

    private function handle(string $type, array $payload, User $user, ?Device $device): array
    {
        return match ($type) {
            'shift.open' => $this->openShift($payload, $user, $device),
            'customer.create' => $this->createCustomer($payload),
            'sale' => $this->recordSale($payload, $user, $device),
            'cash' => $this->cash($payload, $user),
            'kitchen.ticket' => $this->kitchenTicket($payload, $user),
            'receivable.payment' => $this->receivablePayment($payload, $user),
            default => throw ValidationException::withMessages(['type' => "Jenis data tidak dikenal: {$type}"]),
        };
    }

    private function openShift(array $payload, User $user, ?Device $device): array
    {
        $data = $this->validate($payload, [
            'uuid' => ['required', 'uuid'],
            'outlet_id' => ['required', 'integer', Rule::exists('outlets', 'id')->where('tenant_id', $this->context->id())],
            'opening_cash' => ['required', 'integer', 'min:0'],
            'opened_at' => ['nullable', 'date'],
        ]);

        $shift = $this->shifts->open($data['outlet_id'], $user, $data['opening_cash'], $data['uuid'], $device?->id, reuseOpen: false, openedAt: $data['opened_at'] ?? null);

        return ['uuid' => $shift->uuid];
    }

    private function createCustomer(array $payload): array
    {
        $data = $this->validate($payload, [
            'uuid' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $customer = $this->customers->create([
            ...$data,
            'phone' => Phone::normalize($data['phone'] ?? null),
        ]);

        return ['uuid' => $customer->uuid];
    }

    private function recordSale(array $payload, User $user, ?Device $device): array
    {
        $tenantId = $this->context->id();

        // Aturan sama dengan kasir online (diskon tidak ditolak: penjualan sudah terjadi).
        $data = $this->validate($payload, SaleRules::rules($tenantId));

        $existed = Sale::query()->where('uuid', $data['uuid'])->exists();
        $sale = $this->sales->record($data, $user, $device?->id, strict: false);

        if (! $existed) {
            $this->flagNegativeStock($sale, $device);
        }

        return ['uuid' => $sale->uuid, 'number' => $sale->number, 'receipt_url' => $sale->receiptUrl()];
    }

    private function cash(array $payload, User $user): array
    {
        $data = $this->validate($payload, [
            'uuid' => ['required', 'uuid'],
            'shift_uuid' => ['required', 'uuid'],
            'type' => ['required', 'in:in,out'],
            'amount' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $shift = Shift::query()->where('uuid', $data['shift_uuid'])->first()
            ?? throw ValidationException::withMessages(['shift_uuid' => 'Kasir untuk catatan uang ini tidak ditemukan.']);

        $movement = $this->shifts->addCash($shift, $data['type'], $data['amount'], $data['reason'], $user, $data['uuid'], allowClosed: true);

        return ['uuid' => $movement->uuid];
    }

    /** Pesanan meja yang dikirim ke dapur sebelum dibayar (open bill). */
    private function kitchenTicket(array $payload, User $user): array
    {
        $data = $this->validate($payload, [
            'uuid' => ['required', 'uuid'],
            'outlet_id' => ['required', 'integer', Rule::exists('outlets', 'id')->where('tenant_id', $this->context->id())],
            'label' => ['required', 'string', 'max:40'],
            'order_type' => ['nullable', 'string', 'max:20'],
            'note' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['required', 'string', 'max:150'],
            'items.*.qty' => ['required'],
            'items.*.note' => ['nullable', 'string', 'max:150'],
            'items.*.modifiers' => ['nullable', 'array'],
        ]);

        return ['uuid' => $this->kitchen->create($data, $user->id)->uuid];
    }

    /** Pelanggan membayar kasbon di kasir (bisa saat offline). */
    private function receivablePayment(array $payload, User $user): array
    {
        $data = $this->validate($payload, [
            'uuid' => ['required', 'uuid'],
            'customer_uuid' => ['required', 'uuid'],
            'amount' => ['required', 'integer', 'min:1'],
            'payment_method_id' => ['nullable', 'integer', Rule::exists('payment_methods', 'id')->where('tenant_id', $this->context->id())],
            'shift_uuid' => ['nullable', 'uuid'],
        ]);

        $customer = Customer::query()->where('uuid', $data['customer_uuid'])->first()
            ?? throw ValidationException::withMessages(['customer_uuid' => 'Pelanggan tidak ditemukan.']);
        $shiftId = ! empty($data['shift_uuid']) ? Shift::query()->where('uuid', $data['shift_uuid'])->value('id') : null;

        $remaining = $this->receivables->pay($customer, $data['amount'], $data['payment_method_id'] ?? null, $user, $shiftId, $data['uuid']);

        return ['remaining' => $remaining];
    }

    /** Stok minus setelah penjualan offline: dicatat supaya pemilik tahu dan bisa hitung ulang stok. */
    private function flagNegativeStock(Sale $sale, ?Device $device): void
    {
        $productIds = $sale->items()->pluck('product_id')->filter();

        Stock::query()
            ->where('outlet_id', $sale->outlet_id)
            ->whereIn('product_id', $productIds)
            ->where('qty', '<', 0)
            ->with('product:id,name')
            ->get()
            ->each(fn (Stock $stock) => SyncConflict::create([
                'device_id' => $device?->id,
                'outlet_id' => $sale->outlet_id,
                'type' => 'stock_negative',
                'entity_type' => 'sale',
                'entity_uuid' => $sale->uuid,
                'message' => "Stok {$stock->product?->name} minus ".Qty::display($stock->qty).' setelah penjualan offline (nota '.$sale->number.'). Cek & hitung ulang stoknya.',
                'payload' => ['product_id' => $stock->product_id, 'qty' => $stock->qty],
            ]));
    }

    private function conflict(?Device $device, string $type, array $item, string $message): void
    {
        SyncConflict::create([
            'device_id' => $device?->id,
            'outlet_id' => $item['payload']['outlet_id'] ?? null,
            'type' => $type,
            'entity_type' => $item['type'] ?? null,
            'entity_uuid' => $item['payload']['uuid'] ?? null,
            'message' => $message,
            'payload' => $item['payload'] ?? null,
        ]);
    }

    private function validate(array $payload, array $rules): array
    {
        return Validator::make($payload, $rules)->validate();
    }
}
