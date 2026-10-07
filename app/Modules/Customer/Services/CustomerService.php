<?php

namespace App\Modules\Customer\Services;

use App\Modules\Customer\Models\Customer;
use Illuminate\Support\Str;

class CustomerService
{
    /** Membuat pelanggan; uuid bisa dari HP kasir (offline), dan aman dikirim ulang. */
    public function create(array $data): Customer
    {
        if (! empty($data['uuid']) && ($existing = Customer::withTrashed()->where('uuid', $data['uuid'])->first())) {
            return $existing;
        }

        return Customer::create([
            'uuid' => $data['uuid'] ?? (string) Str::uuid(),
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'notes' => $data['notes'] ?? null,
            ...array_intersect_key($data, array_flip(['price_level_id', 'credit_limit'])),
        ]);
    }

    public function update(Customer $customer, array $data): Customer
    {
        $customer->update([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'notes' => $data['notes'] ?? null,
            ...array_intersect_key($data, array_flip(['price_level_id', 'credit_limit'])),
        ]);

        return $customer;
    }
}
