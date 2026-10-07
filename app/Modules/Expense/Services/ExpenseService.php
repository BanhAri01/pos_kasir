<?php

namespace App\Modules\Expense\Services;

use App\Models\User;
use App\Modules\Expense\Models\Expense;
use App\Modules\Expense\Models\ExpenseCategory;
use App\Modules\Pos\Models\CashMovement;
use App\Modules\Pos\Models\Shift;
use App\Modules\Pos\Services\ShiftService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Catat pengeluaran. Kalau uangnya diambil dari laci kasir yang sedang buka,
 * otomatis tercatat sebagai "uang keluar" di kasir supaya hitungan tutup kasir tetap pas.
 */
class ExpenseService
{
    public function __construct(private ShiftService $shifts) {}

    /** Jenis pengeluaran bawaan dibuat saat pertama kali dibutuhkan. */
    public function ensureDefaultCategories(): void
    {
        if (ExpenseCategory::query()->exists()) {
            return;
        }

        foreach (ExpenseCategory::DEFAULTS as $i => $name) {
            ExpenseCategory::create(['name' => $name, 'sort_order' => $i]);
        }
    }

    /**
     * @param  array{outlet_id:int, expense_category_id?:int|null, new_category?:string|null, spent_on:string, amount:int,
     *               payment_method_id?:int|null, from_cash_drawer?:bool, note?:string|null, uuid?:string|null}  $data
     */
    public function create(array $data, User $user): Expense
    {
        if (! empty($data['uuid']) && ($existing = Expense::query()->where('uuid', $data['uuid'])->first())) {
            return $existing;
        }

        return DB::transaction(function () use ($data, $user) {
            $categoryId = $data['expense_category_id'] ?? null;
            if (! empty($data['new_category'])) {
                $categoryId = ExpenseCategory::firstOrCreate(['name' => trim($data['new_category'])])->id;
            }

            $movement = null;
            if (! empty($data['from_cash_drawer'])) {
                // Laci milik sendiri dulu, kalau tidak ada: kasir lain yang sedang buka di outlet ini.
                $shift = $this->shifts->currentFor($user, $data['outlet_id'])
                    ?? Shift::query()->where('outlet_id', $data['outlet_id'])->where('status', 'open')->latest('id')->first();
                if (! $shift) {
                    throw ValidationException::withMessages(['from_cash_drawer' => 'Kasir belum dibuka, jadi uang tidak bisa diambil dari laci. Matikan pilihan ini atau buka kasir dulu.']);
                }
                $category = $categoryId ? ExpenseCategory::query()->find($categoryId)?->name : null;
                $movement = $this->shifts->addCash($shift, 'out', (int) $data['amount'], 'Pengeluaran: '.($category ?? 'lain-lain').(! empty($data['note']) ? " ({$data['note']})" : ''), $user);
            }

            return Expense::create([
                'outlet_id' => $data['outlet_id'],
                'uuid' => $data['uuid'] ?? (string) Str::uuid(),
                'expense_category_id' => $categoryId,
                'spent_on' => $data['spent_on'],
                'amount' => (int) $data['amount'],
                'payment_method_id' => $data['payment_method_id'] ?? null,
                'cash_movement_id' => $movement?->id,
                'note' => $data['note'] ?? null,
                'user_id' => $user->id,
            ]);
        });
    }

    public function delete(Expense $expense): void
    {
        DB::transaction(function () use ($expense) {
            // Uang dari laci yang sudah tercatat tidak dihapus kalau kasirnya sudah ditutup (hitungan kasir sudah final).
            if ($expense->cash_movement_id && ($movement = CashMovement::query()->with('shift')->find($expense->cash_movement_id)) && $movement->shift?->status === 'open') {
                $movement->delete();
            }
            $expense->delete();
        });
    }
}
