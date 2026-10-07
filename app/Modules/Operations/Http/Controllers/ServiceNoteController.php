<?php

namespace App\Modules\Operations\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Modules\Customer\Models\Customer;
use App\Modules\Operations\Models\CustomerServiceNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Catatan layanan pelanggan, mis. "potong pendek no. 2, tidak suka pomade". */
class ServiceNoteController extends Controller
{
    public function store(Request $request, Customer $customer, TenantContext $context): RedirectResponse
    {
        $data = $request->validate([
            'note' => ['required', 'string', 'max:1000'],
            'staff_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', $context->id())],
        ], ['note.required' => 'Catatannya belum diisi.']);

        CustomerServiceNote::create([...$data, 'customer_id' => $customer->id, 'created_by' => $request->user()->id]);

        return back()->with('success', 'Catatan layanan sudah disimpan.');
    }

    public function destroy(CustomerServiceNote $note): RedirectResponse
    {
        $note->delete();

        return back()->with('success', 'Catatan dihapus.');
    }
}
