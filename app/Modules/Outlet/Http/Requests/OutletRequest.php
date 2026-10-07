<?php

namespace App\Modules\Outlet\Http\Requests;

use App\Core\Support\Phone;
use App\Models\Outlet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Data outlet + pengaturan pajak, biaya layanan, dan struk.
 * Persen diisi seperti biasa ("11" atau "2,5"), disimpan sebagai basis point (1100 / 250).
 */
class OutletRequest extends FormRequest
{
    /** Cek izin dulu (OutletPolicy) sebelum validasi, supaya yang tidak berhak langsung ditolak. */
    public function authorize(): bool
    {
        $outlet = $this->route('outlet');

        return $outlet
            ? $this->user()->can('update', $outlet)
            : $this->user()->can('create', Outlet::class);
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('phone')) {
            $this->merge(['phone' => Phone::normalize($this->input('phone')) ?? $this->input('phone')]);
        }

        foreach (['tax_rate', 'service_charge'] as $field) {
            if ($this->filled($field)) {
                $this->merge([$field => str_replace(',', '.', (string) $this->input($field))]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(array_keys(Outlet::TYPES))],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'regex:/^628\d{7,12}$/'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_inclusive' => ['boolean'],
            'service_charge' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'receipt_header' => ['nullable', 'string', 'max:255'],
            'receipt_footer' => ['nullable', 'string', 'max:255'],
            'receipt_paper' => ['nullable', Rule::in(['58', '80'])],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama outlet belum diisi.',
            'name.max' => 'Nama outlet terlalu panjang. Maksimal 100 huruf.',
            'phone.regex' => 'No HP sepertinya salah. Contoh yang benar: 0812 3456 7890.',
            'tax_rate.numeric' => 'Pajak harus berupa angka. Contoh: 10 atau 11.',
            'tax_rate.max' => 'Pajak tidak boleh lebih dari 100%.',
            'service_charge.numeric' => 'Biaya layanan harus berupa angka. Contoh: 5.',
            'service_charge.max' => 'Biaya layanan tidak boleh lebih dari 100%.',
        ];
    }

    /** Data siap simpan (persen -> basis point). */
    public function outletData(): array
    {
        $data = $this->validated();

        $result = [
            'name' => $data['name'],
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
        ];

        // Jenis tempat (toko / gudang) hanya dikirim kalau modul Banyak Gudang menyala.
        if (! empty($data['type'])) {
            $result['type'] = $data['type'];
        }

        if ($this->has('tax_rate')) {
            $result['tax_rate_bp'] = (int) round(((float) ($data['tax_rate'] ?? 0)) * 100);
            $result['tax_inclusive'] = (bool) ($data['tax_inclusive'] ?? false);
            $result['service_charge_bp'] = (int) round(((float) ($data['service_charge'] ?? 0)) * 100);
            $result['receipt_header'] = $data['receipt_header'] ?? null;
            $result['receipt_footer'] = $data['receipt_footer'] ?? null;
            $result['receipt_paper'] = $data['receipt_paper'] ?? '58';
        }

        return $result;
    }
}
