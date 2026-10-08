<?php

namespace App\Modules\Staff\Http\Controllers;

use App\Enums\Role;
use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\User;
use App\Modules\Billing\Services\PlanGuard;
use App\Modules\Staff\Http\Requests\StaffRequest;
use App\Modules\Staff\Http\Resources\StaffResource;
use App\Modules\Staff\Services\StaffService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StaffController extends Controller
{
    public function __construct(private StaffService $service) {}

    public function index(): Response
    {
        $this->authorize('viewAny', User::class);

        $staff = User::sameTenant()->with(['roles', 'outlets'])->orderBy('name')->get();

        return Inertia::render('Staff/Index', [
            'staff' => StaffResource::collection($staff)->resolve(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('Staff/Form', $this->formData($request, null));
    }

    // store & update: izin sudah dicek di StaffRequest::authorize().
    public function store(StaffRequest $request, PlanGuard $guard, TenantContext $context): RedirectResponse
    {
        $guard->ensureCanAddStaff($context->get());
        $staff = $this->service->create($request->validated());

        return redirect()->route('staff.index')->with('success', "{$staff->name} sudah ditambahkan.");
    }

    public function edit(Request $request, User $staff): Response
    {
        $this->authorize('update', $staff);

        return Inertia::render('Staff/Form', $this->formData($request, $staff->load(['roles', 'outlets'])));
    }

    public function update(StaffRequest $request, User $staff): RedirectResponse
    {
        $this->service->update($staff, $request->validated());

        return redirect()->route('staff.index')->with('success', "Data {$staff->name} sudah disimpan.");
    }

    public function destroy(User $staff): RedirectResponse
    {
        $this->authorize('delete', $staff);

        $this->service->delete($staff);

        return redirect()->route('staff.index')->with('undo', [
            'message' => "{$staff->name} sudah dihapus.",
            'url' => route('staff.restore', $staff->id),
        ]);
    }

    public function restore(int $id): RedirectResponse
    {
        $staff = User::sameTenant()->onlyTrashed()->findOrFail($id);
        $this->authorize('restore', $staff);

        $this->service->restore($staff);

        return redirect()->route('staff.index')->with('success', "{$staff->name} sudah dikembalikan.");
    }

    private function formData(Request $request, ?User $staff): array
    {
        return [
            'staff' => $staff ? StaffResource::make($staff)->resolve() : null,
            'roles' => collect($request->user()->role()?->canAssign() ?? [])->map(fn (Role $r) => [
                'value' => $r->value,
                'label' => $r->label(),
                'description' => $r->description(),
            ])->values(),
            'outlets' => Outlet::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ];
    }
}
