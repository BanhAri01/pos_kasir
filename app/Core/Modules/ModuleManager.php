<?php

namespace App\Core\Modules;

use App\Models\Module;
use App\Models\Tenant;
use App\Modules\Billing\Services\Plans;
use Illuminate\Support\Facades\DB;

/**
 * Menyalakan / mematikan modul (fitur) untuk satu tenant.
 */
class ModuleManager
{
    /** Nyalakan modul bawaan sesuai jenis usaha (dipanggil saat pendaftaran). */
    public function enableDefaults(Tenant $tenant): void
    {
        $modules = $tenant->businessType->modules()
            ->where('is_active', true)
            ->where('is_core', false)
            ->get();

        $now = now();
        $tenant->modules()->syncWithoutDetaching(
            $modules->mapWithKeys(fn (Module $m) => [
                $m->id => ['is_enabled' => true, 'enabled_at' => $now],
            ])->all()
        );

        $tenant->flushModuleCache();
    }

    /**
     * Nyalakan modul. Modul yang dibutuhkan ikut dinyalakan otomatis,
     * supaya pengguna tidak perlu memahami urutan.
     *
     * @return list<string> nama modul yang ikut dinyalakan (untuk diberitahukan ke pengguna)
     */
    public function enable(Tenant $tenant, string $code): array
    {
        $module = $this->findToggleable($code);
        $this->ensurePlanAllows($tenant, $module);
        $alsoEnabled = [];

        DB::transaction(function () use ($tenant, $module, &$alsoEnabled) {
            foreach ($module->depends_on ?? [] as $dependencyCode) {
                if (! $tenant->hasModule($dependencyCode)) {
                    $dependency = $this->findToggleable($dependencyCode);
                    $this->ensurePlanAllows($tenant, $dependency);
                    $this->setEnabled($tenant, $dependency, true);
                    $alsoEnabled[] = $dependency->name;
                }
            }

            $this->setEnabled($tenant, $module, true);
        });

        activity('fitur')->performedOn($module)->log("Menyalakan fitur {$module->name}");

        return $alsoEnabled;
    }

    public function disable(Tenant $tenant, string $code): void
    {
        $module = $this->findToggleable($code);

        // Jumlah modul sedikit, jadi cukup disaring di PHP (aman untuk MySQL & SQLite).
        $dependents = Module::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (Module $m) => in_array($code, $m->depends_on ?? [], true)
                && $tenant->hasModule($m->code));

        if ($dependents->isNotEmpty()) {
            $names = $dependents->pluck('name')->join(', ', ' dan ');

            throw new ModuleException(
                "Fitur {$module->name} masih dibutuhkan oleh fitur {$names}. Matikan fitur {$names} dulu."
            );
        }

        $this->setEnabled($tenant, $module, false);

        activity('fitur')->performedOn($module)->log("Mematikan fitur {$module->name}");
    }

    private function setEnabled(Tenant $tenant, Module $module, bool $enabled): void
    {
        $tenant->modules()->syncWithoutDetaching([
            $module->id => [
                'is_enabled' => $enabled,
                'enabled_at' => $enabled ? now() : null,
            ],
        ]);

        $tenant->flushModuleCache();
    }

    private function ensurePlanAllows(Tenant $tenant, Module $module): void
    {
        if (! $tenant->planAllowsModule($module->code)) {
            $required = Plans::label(Plans::requiredForModule($module->code));

            throw new ModuleException("Fitur {$module->name} tersedia mulai paket {$required}. Naikkan paket di menu Langganan.");
        }
    }

    private function findToggleable(string $code): Module
    {
        $module = Module::query()->where('code', $code)->where('is_active', true)->first();

        if (! $module) {
            throw new ModuleException('Fitur ini tidak ditemukan.');
        }

        if ($module->is_core) {
            throw new ModuleException("Fitur {$module->name} adalah fitur utama dan selalu menyala.");
        }

        return $module;
    }
}
