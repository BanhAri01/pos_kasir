import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Akses cepat ke user, usaha, hak akses, dan modul aktif.
 *
 *   const { can, hasModule } = useAuth();
 *   v-if="can('manage_staff')"     v-if="hasModule('tables')"
 */
export function useAuth() {
    const page = usePage();

    const user = computed(() => page.props.auth?.user ?? null);
    const tenant = computed(() => page.props.tenant ?? null);

    const can = (permission) => user.value?.permissions?.includes(permission) ?? false;
    const hasModule = (code) => tenant.value?.modules?.includes(code) ?? false;

    return { user, tenant, can, hasModule };
}
