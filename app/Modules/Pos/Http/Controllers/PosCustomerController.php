<?php

namespace App\Modules\Pos\Http\Controllers;

use App\Core\Support\Phone;
use App\Http\Controllers\Controller;
use App\Modules\Customer\Http\Requests\CustomerRequest;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Services\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Cari & tambah pelanggan langsung dari layar kasir. */
class PosCustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $term = trim($request->string('q')->toString());
        $phone = Phone::normalize($term);

        $customers = Customer::query()
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$term}%")
                ->when($phone, fn ($w2) => $w2->orWhere('phone', $phone))
                ->orWhere('member_code', $term)))
            ->orderBy('name')
            ->limit(20)
            ->get(['uuid', 'name', 'phone']);

        return response()->json(['data' => $customers->map(fn ($c) => [
            'uuid' => $c->uuid, 'name' => $c->name, 'phone' => $c->phone, 'phone_display' => Phone::display($c->phone),
        ])]);
    }

    public function store(CustomerRequest $request, CustomerService $service): JsonResponse
    {
        $customer = $service->create($request->validated());

        return response()->json(['data' => [
            'uuid' => $customer->uuid, 'name' => $customer->name, 'phone' => $customer->phone, 'phone_display' => Phone::display($customer->phone),
        ]], 201);
    }
}
