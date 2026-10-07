<?php

namespace App\Modules\Outlet\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Modules\Outlet\Http\Requests\OutletRequest;
use App\Modules\Outlet\Http\Resources\OutletResource;
use App\Modules\Outlet\Services\OutletService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class OutletController extends Controller
{
    public function __construct(private OutletService $service) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Outlet::class);

        $outlets = Outlet::query()->withCount('users')->orderBy('name')->get();

        return Inertia::render('Outlets/Index', [
            'outlets' => OutletResource::collection($outlets)->resolve(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Outlet::class);

        return Inertia::render('Outlets/Form', ['outlet' => null]);
    }

    // store & update: izin sudah dicek di OutletRequest::authorize().
    public function store(OutletRequest $request): RedirectResponse
    {
        $outlet = $this->service->create($request->outletData(), $request->user());

        return redirect()->route('outlets.index')->with('success', "Outlet {$outlet->name} sudah disimpan.");
    }

    public function edit(Outlet $outlet): Response
    {
        $this->authorize('update', $outlet);

        return Inertia::render('Outlets/Form', [
            'outlet' => OutletResource::make($outlet)->resolve(),
        ]);
    }

    public function update(OutletRequest $request, Outlet $outlet): RedirectResponse
    {
        $this->service->update($outlet, $request->outletData());

        return redirect()->route('outlets.index')->with('success', 'Perubahan outlet sudah disimpan.');
    }

    public function destroy(Outlet $outlet): RedirectResponse
    {
        $this->authorize('delete', $outlet);

        $this->service->delete($outlet);

        return redirect()->route('outlets.index')->with('undo', [
            'message' => "Outlet {$outlet->name} sudah dihapus.",
            'url' => route('outlets.restore', $outlet->id),
        ]);
    }

    public function restore(int $id): RedirectResponse
    {
        $outlet = Outlet::onlyTrashed()->findOrFail($id);
        $this->authorize('restore', $outlet);

        $this->service->restore($outlet);

        return redirect()->route('outlets.index')->with('success', "Outlet {$outlet->name} sudah dikembalikan.");
    }
}
