<script setup>
/**
 * Atur Fitur: sakelar besar per fitur, dengan penjelasan bahasa sehari-hari.
 * Fitur yang disarankan untuk jenis usaha ini diberi tanda bintang.
 */
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { CircleCheck, Star } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/ui/AppIcon.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import ToggleSwitch from '@/Components/ui/ToggleSwitch.vue';

defineOptions({ layout: AppLayout });

defineProps({
    businessTypeName: { type: String, required: true },
    coreModules: { type: Array, required: true },
    optionalModules: { type: Array, required: true },
});

const saving = ref(null);

function toggle(module, enabled) {
    saving.value = module.code;
    router.put(
        route('modules.update', module.code),
        { enabled },
        { preserveScroll: true, onFinish: () => (saving.value = null) },
    );
}
</script>

<template>
    <Head title="Atur Fitur" />
    <PageHeader
        title="Atur Fitur"
        subtitle="Nyalakan fitur yang Anda butuhkan saja, supaya aplikasi tetap sederhana."
        :back-href="route('more')"
        help="modules"
    />

    <section>
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-xl font-extrabold text-ink">Fitur tambahan</h2>
            <p class="flex items-center gap-2 rounded-full bg-accent-soft px-3 py-1 text-base font-bold text-accent-ink">
                <Star :size="20" class="shrink-0 fill-current" aria-hidden="true" />
                Disarankan untuk {{ businessTypeName }}
            </p>
        </div>
        <ul class="grid gap-3 xl:grid-cols-2">
            <!-- flex-wrap: di HP sempit sakelar turun ke baris sendiri, teks tidak terjepit -->
            <li v-for="module in optionalModules" :key="module.code" class="card flex flex-wrap items-center gap-x-4 gap-y-2 p-4">
                <span
                    class="flex size-12 shrink-0 items-center justify-center rounded-2xl transition-colors"
                    :class="module.enabled ? 'bg-primary-soft text-primary-ink' : 'bg-surface-2 text-ink-soft'"
                >
                    <AppIcon :name="module.icon" :size="26" />
                </span>
                <div class="min-w-48 flex-1">
                    <p class="flex items-center gap-2 font-display text-lg font-extrabold text-ink">
                        {{ module.name }}
                        <Star v-if="module.recommended" :size="20" class="shrink-0 fill-accent text-accent" aria-label="Disarankan" />
                    </p>
                    <p class="text-base text-ink-soft">{{ module.description }}</p>
                </div>
                <Link
                    v-if="module.required_plan"
                    :href="route('billing.index')"
                    class="pressable ml-auto rounded-full bg-accent-soft px-3 py-2 text-base font-bold text-accent-ink"
                >
                    Paket {{ module.required_plan }}
                </Link>
                <ToggleSwitch
                    v-else
                    class="ml-auto"
                    :model-value="module.enabled"
                    :label="module.name"
                    :disabled="saving === module.code"
                    @update:model-value="(v) => toggle(module, v)"
                />
            </li>
        </ul>
    </section>

    <section class="mt-10">
        <h2 class="mb-1 text-xl font-extrabold text-ink">Fitur utama</h2>
        <p class="mb-4 text-lg text-ink-soft">Selalu menyala untuk semua usaha.</p>
        <ul class="grid gap-2 md:grid-cols-2">
            <li v-for="module in coreModules" :key="module.code" class="flex items-center gap-3 rounded-2xl bg-surface-2 p-4">
                <CircleCheck :size="26" class="shrink-0 text-primary-ink" aria-hidden="true" />
                <div>
                    <p class="text-lg font-bold text-ink">{{ module.name }}</p>
                    <p class="text-base text-ink-soft">{{ module.description }}</p>
                </div>
            </li>
        </ul>
    </section>
</template>
