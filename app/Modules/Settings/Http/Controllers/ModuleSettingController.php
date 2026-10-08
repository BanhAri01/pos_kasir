<?php

namespace App\Modules\Settings\Http\Controllers;

use App\Core\Modules\ModuleException;
use App\Core\Modules\ModuleManager;
use App\Core\Tenancy\TenantContext;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Modules\Billing\Services\Plans;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman "Atur Fitur": nyalakan / matikan modul dengan sakelar besar.
 */
class ModuleSettingController extends Controller
{
    public function __construct(
        private ModuleManager $modules,
        private TenantContext $context,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can(Permission::ManageModules->value), 403);

        $tenant = $this->context->get();
        $enabled = $tenant->enabledModuleCodes();
        $recommended = $tenant->businessType->modules()->pluck('code')->all();

        $all = Module::query()->where('is_active', true)->orderBy('sort_order')->get();
        $map = fn (Module $m) => [
            'code' => $m->code,
            'name' => $m->name,
            'description' => $m->description,
            'icon' => $m->icon,
            'enabled' => in_array($m->code, $enabled, true),
            'recommended' => in_array($m->code, $recommended, true),
            'required_plan' => $tenant->planAllowsModule($m->code) ? null : Plans::label(Plans::requiredForModule($m->code)),
        ];

        return Inertia::render('Settings/Modules', [
            'businessTypeName' => $tenant->businessType->name,
            'coreModules' => $all->where('is_core', true)->map($map)->values(),
            'optionalModules' => $all->where('is_core', false)->map($map)->values(),
        ]);
    }

    public function update(Request $request, string $code): RedirectResponse
    {
        abort_unless($request->user()->can(Permission::ManageModules->value), 403);

        $request->validate(['enabled' => ['required', 'boolean']]);

        $tenant = $this->context->get();
        $name = Module::query()->where('code', $code)->value('name');

        try {
            if ($request->boolean('enabled')) {
                $also = $this->modules->enable($tenant, $code);
                $message = "Fitur {$name} sudah menyala.";
                if ($also) {
                    $message .= ' Fitur '.implode(', ', $also).' juga ikut dinyalakan karena dibutuhkan.';
                }
            } else {
                $this->modules->disable($tenant, $code);
                $message = "Fitur {$name} sudah dimatikan. Data lama tetap tersimpan aman.";
            }
        } catch (ModuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $message);
    }
}
