<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Core\Tenancy\TenantContext;
use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi bersama untuk daftar barang di dokumen stok
 * (stok masuk/keluar, hitung stok, kirim stok).
 */
abstract class StockItemsRequest extends FormRequest
{
    /** Nama kolom jumlah di setiap baris: 'qty' atau 'counted_qty'. */
    protected string $qtyField = 'qty';

    /** Hitung stok boleh 0; masuk/keluar/kirim harus lebih dari 0. */
    protected bool $allowZero = false;

    public function authorize(): bool
    {
        return $this->user()->can(Permission::ManageStock->value);
    }

    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))->map(function ($item) {
            if (isset($item[$this->qtyField])) {
                $item[$this->qtyField] = str_replace(',', '.', (string) $item[$this->qtyField]);
            }

            return $item;
        })->all();

        $this->merge(['items' => $items]);
    }

    protected function itemRules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => [
                'required', 'integer', 'distinct',
                Rule::exists('products', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')->where('track_stock', true),
            ],
            "items.*.{$this->qtyField}" => ['required', 'numeric', $this->allowZero ? 'min:0' : 'gt:0', 'max:9999999'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Pilih minimal satu barang dulu.',
            'items.min' => 'Pilih minimal satu barang dulu.',
            'items.*.product_id.exists' => 'Ada barang yang tidak ditemukan atau tidak dihitung stoknya.',
            'items.*.product_id.distinct' => 'Ada barang yang dipilih dua kali. Gabungkan jumlahnya di satu baris.',
            "items.*.{$this->qtyField}.required" => 'Jumlah barang belum diisi.',
            "items.*.{$this->qtyField}.numeric" => 'Jumlah harus berupa angka. Contoh: 5 atau 2,5.',
            "items.*.{$this->qtyField}.gt" => 'Jumlah harus lebih dari 0.',
            "items.*.{$this->qtyField}.min" => 'Jumlah tidak boleh minus.',
        ];
    }
}
