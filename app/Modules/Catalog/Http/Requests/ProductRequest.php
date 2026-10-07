<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Core\Tenancy\TenantContext;
use App\Modules\Catalog\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $product
            ? $this->user()->can('update', $product)
            : $this->user()->can('create', Product::class);
    }

    protected function prepareForValidation(): void
    {
        // Jumlah boleh memakai koma ("1,5").
        foreach (['initial_stock', 'min_stock', 'pack_size', 'consignment_share'] as $field) {
            if ($this->filled($field)) {
                $this->merge([$field => str_replace(',', '.', (string) $this->input($field))]);
            }
        }

        foreach (['recipe' => 'qty', 'units' => 'conversion_qty', 'prices' => 'min_qty'] as $list => $field) {
            if (is_array($this->input($list))) {
                $this->merge([$list => array_map(
                    fn ($row) => is_array($row) && isset($row[$field]) ? [...$row, $field => str_replace(',', '.', (string) $row[$field])] : $row,
                    array_values($this->input($list)),
                )]);
            }
        }
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();
        $product = $this->route('product');

        return [
            'name' => ['required', 'string', 'max:150'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
            'new_category' => ['nullable', 'string', 'max:100'],
            'type' => ['required', Rule::in(['goods', 'service', 'ingredient'])],
            'price' => ['required', 'integer', 'min:0', 'max:999999999999'],
            'cost_price' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'base_unit_id' => ['nullable', 'integer', Rule::exists('units', 'id')->where('tenant_id', $tenantId)],
            'pricing_mode' => ['nullable', Rule::in(Product::PRICING_MODES)],
            'track_stock' => ['boolean'],
            'initial_stock' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'min_stock' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'pack_size' => ['nullable', 'numeric', 'gt:0', 'max:9999999'],
            'pack_name' => ['nullable', 'string', 'max:20'],
            'is_packaging' => ['boolean'],
            'pack_weight_fixed' => ['boolean'],
            'consignor_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->where('tenant_id', $tenantId)],
            'consignment_share' => ['nullable', 'required_with:consignor_id', 'numeric', 'min:0', 'max:100'],
            'track_batch' => ['boolean'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:20160'],
            'code' => ['nullable', 'string', 'max:50'],
            'barcode' => [
                'nullable', 'string', 'max:64',
                Rule::unique('products', 'barcode')->where('tenant_id', $tenantId)->whereNull('deleted_at')->ignore($product?->id),
            ],
            'image' => ['nullable', 'image', 'max:8192'],
            'remove_image' => ['boolean'],
            'description' => ['nullable', 'string', 'max:1000'],

            // Bagian tambahan (hanya dipakai bila modulnya aktif).
            'recipe' => ['sometimes', 'array', 'max:50'],
            'recipe.*.ingredient_id' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')->where('tenant_id', $tenantId)],
            'recipe.*.qty' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'units' => ['sometimes', 'array', 'max:10'],
            'units.*.unit_id' => ['required', 'integer', 'distinct', Rule::exists('units', 'id')->where('tenant_id', $tenantId)],
            'units.*.conversion_qty' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'units.*.price' => ['required', 'integer', 'min:0', 'max:999999999999'],
            'units.*.barcode' => ['nullable', 'string', 'max:64'],
            'prices' => ['sometimes', 'array', 'max:30'],
            'prices.*.price_level_id' => ['nullable', 'integer', Rule::exists('price_levels', 'id')->where('tenant_id', $tenantId)],
            'prices.*.unit_id' => ['nullable', 'integer'],
            'prices.*.min_qty' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'prices.*.price' => ['required', 'integer', 'min:0', 'max:999999999999'],
            'modifier_group_ids' => ['sometimes', 'array'],
            'modifier_group_ids.*' => ['integer', Rule::exists('modifier_groups', 'id')->where('tenant_id', $tenantId)],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama barang belum diisi.',
            'price.required' => 'Harga jual belum diisi. Isi 0 kalau harganya ditentukan saat jualan.',
            'price.integer' => 'Harga jual harus berupa angka.',
            'price.min' => 'Harga jual tidak boleh minus.',
            'cost_price.min' => 'Modal tidak boleh minus.',
            'category_id.exists' => 'Kategori tidak ditemukan. Silakan pilih lagi.',
            'initial_stock.numeric' => 'Stok awal harus berupa angka. Contoh: 10 atau 2,5.',
            'initial_stock.min' => 'Stok awal tidak boleh minus.',
            'min_stock.numeric' => 'Batas stok menipis harus berupa angka.',
            'pack_size.numeric' => 'Isi 1 karung harus berupa angka. Contoh: 50',
            'pack_size.gt' => 'Isi 1 karung harus lebih dari 0.',
            'consignment_share.required_with' => 'Isi bagian toko (persen) untuk barang titipan.',
            'consignment_share.max' => 'Bagian toko maksimal 100%.',
            'barcode.unique' => 'Barcode ini sudah dipakai barang lain. Periksa lagi barcode-nya.',
            'image.image' => 'File yang dipilih bukan foto. Pilih foto (JPG atau PNG).',
            'image.max' => 'Foto terlalu besar. Pilih foto lain yang lebih kecil.',
            'duration_minutes.min' => 'Lama layanan minimal 1 menit.',
            'recipe.*.ingredient_id.distinct' => 'Bahan yang sama dipilih dua kali.',
            'recipe.*.qty.gt' => 'Jumlah bahan harus lebih dari 0.',
            'units.*.unit_id.distinct' => 'Satuan yang sama dipilih dua kali.',
            'units.*.conversion_qty.gt' => 'Isi per satuan harus lebih dari 0. Contoh: 1 dus isi 40.',
            'units.*.price.required' => 'Harga per satuan belum diisi.',
            'prices.*.price.required' => 'Harga grosir belum diisi.',
            'prices.*.min_qty.gt' => 'Minimal beli harus lebih dari 0.',
        ];
    }
}
