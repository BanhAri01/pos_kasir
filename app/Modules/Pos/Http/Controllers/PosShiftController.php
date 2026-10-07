<?php

namespace App\Modules\Pos\Http\Controllers;

use App\Core\Support\Rupiah;
use App\Core\Tenancy\CurrentOutlet;
use App\Http\Controllers\Controller;
use App\Modules\Auth\Services\DeviceService;
use App\Modules\Pos\Models\Shift;
use App\Modules\Pos\Services\ShiftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Buka / tutup kasir dan uang masuk/keluar dari layar kasir. */
class PosShiftController extends Controller
{
    public function __construct(private ShiftService $shifts) {}

    public function open(Request $request, CurrentOutlet $outlet, DeviceService $devices): JsonResponse
    {
        $data = $request->validate([
            'uuid' => ['nullable', 'uuid'],
            'opening_cash' => ['required', 'integer', 'min:0'],
        ], ['opening_cash.required' => 'Isi uang awal di laci. Isi 0 kalau kosong.']);

        $device = $devices->fromRequest($request);
        $shift = $this->shifts->open(
            $outlet->id($request->user()), $request->user(), (int) $data['opening_cash'], $data['uuid'] ?? null,
            $device?->tenant_id === $request->user()->tenant_id ? $device->id : null,
        );

        return response()->json(['data' => [
            'uuid' => $shift->uuid,
            'opened_at' => $shift->opened_at->toIso8601String(),
            'opening_cash' => $shift->opening_cash,
        ]], 201);
    }

    public function summary(string $uuid): JsonResponse
    {
        $shift = $this->find($uuid);

        return response()->json(['data' => [
            ...$this->shifts->summary($shift),
            'uuid' => $shift->uuid,
            'status' => $shift->status,
        ]]);
    }

    public function cash(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'uuid' => ['nullable', 'uuid'],
            'type' => ['required', 'in:in,out'],
            'amount' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
        ], [
            'amount.min' => 'Jumlah uang harus lebih dari 0.',
            'reason.required' => 'Tulis untuk apa uang ini. Contoh: beli es batu.',
        ]);

        $this->shifts->addCash($this->find($uuid), $data['type'], (int) $data['amount'], $data['reason'], $request->user(), $data['uuid'] ?? null);

        $label = $data['type'] === 'in' ? 'Uang masuk' : 'Uang keluar';

        return response()->json(['message' => "{$label} ".Rupiah::format((int) $data['amount']).' sudah dicatat.']);
    }

    public function close(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'counted_cash' => ['required', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ], ['counted_cash.required' => 'Hitung uang di laci, lalu isi jumlahnya.']);

        $shift = $this->shifts->close($this->find($uuid), (int) $data['counted_cash'], $data['note'] ?? null, $request->user());

        $difference = $shift->cash_difference;
        $message = match (true) {
            $difference === 0 => 'Kasir sudah ditutup. Uang di laci pas, terima kasih!',
            $difference < 0 => 'Kasir sudah ditutup. Uang kurang '.Rupiah::format(-$difference).'.',
            default => 'Kasir sudah ditutup. Uang lebih '.Rupiah::format($difference).'.',
        };

        return response()->json(['message' => $message, 'data' => [
            'expected_cash' => $shift->expected_cash,
            'counted_cash' => $shift->counted_cash,
            'cash_difference' => $difference,
        ]]);
    }

    private function find(string $uuid): Shift
    {
        return Shift::query()->where('uuid', $uuid)->firstOrFail();
    }
}
