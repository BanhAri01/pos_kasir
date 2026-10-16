<script setup>
/**
 * Jenis Telur: tandai barang mana yang jadi Telur Campur (dibeli dari peternak)
 * dan mana yang jadi Hasil Sortir (Telur Besar, Kecil, Retak, dll.).
 */
import { computed, reactive } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Package } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    products: { type: Array, required: true },
    roles: { type: Object, required: true },
});

const roleMap = reactive(
    Object.fromEntries(props.products.map((p) => [p.id, p.role ?? null])),
);

const mixedIds = computed(() =>
    props.products.filter((p) => roleMap[p.id] === 'mixed').map((p) => p.id),
);
const gradeIds = computed(() =>
    props.products.filter((p) => roleMap[p.id] === 'grade').map((p) => p.id),
);

const form = useForm({
    mixed: [],
    grades: [],
});

const firstError = computed(() => {
    const errs = Object.values(form.errors);
    return errs.length ? errs[0] : '';
});

function setRole(productId, role) {
    roleMap[productId] = roleMap[productId] === role ? null : role;
}

function save() {
    form.transform(() => ({
        mixed: mixedIds.value,
        grades: gradeIds.value,
    })).put(route('egg.types.update'));
}
</script>

<template>
    <Head title="Jenis Telur" />
    <div class="mx-auto max-w-3xl">
        <PageHeader
            title="Jenis Telur"
            subtitle="Pilih barang mana yang dibeli sebagai telur campur dan mana yang hasil sortir."
            :back-href="route('egg.prices')"
        />

        <EmptyState
            v-if="!products.length"
            title="Belum ada data barang"
            message="Tambahkan dulu barang telur di menu Barang, lalu atur jenisnya di sini."
        >
            <template #icon><Package :size="48" aria-hidden="true" /></template>
            <BigButton :href="route('products.create')">Tambah Barang</BigButton>
        </EmptyState>

        <form v-else class="flex flex-col gap-5" @submit.prevent="save">
            <div class="card flex flex-wrap items-center justify-between gap-3 p-4 text-lg">
                <span>
                    Telur campur: <strong class="text-ink">{{ mixedIds.length }} barang</strong>
                </span>
                <span>
                    Hasil sortir: <strong class="text-ink">{{ gradeIds.length }} jenis</strong>
                </span>
            </div>

            <p v-if="firstError" role="alert" class="rounded-2xl bg-danger-soft p-4 text-lg font-bold text-danger-ink">
                {{ firstError }}
            </p>

            <ul class="flex flex-col gap-3">
                <li
                    v-for="p in products"
                    :key="p.id"
                    class="card flex flex-col gap-3 p-5"
                >
                    <div class="flex items-baseline justify-between gap-3">
                        <span class="text-xl font-extrabold text-ink">{{ p.name }}</span>
                        <span v-if="p.unit" class="text-base font-bold text-ink-soft">Satuan {{ p.unit }}</span>
                    </div>

                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-3" role="group" :aria-label="`Peran untuk ${p.name}`">
                        <button
                            type="button"
                            class="pressable min-h-touch rounded-2xl border-2 px-4 text-lg font-bold"
                            :class="
                                roleMap[p.id] === 'mixed'
                                    ? 'border-primary bg-accent-soft text-accent-ink'
                                    : 'border-line bg-surface text-ink hover:border-ink-soft'
                            "
                            :aria-pressed="roleMap[p.id] === 'mixed'"
                            @click="setRole(p.id, 'mixed')"
                        >
                            {{ roles.mixed || 'Telur campur' }}
                        </button>
                        <button
                            type="button"
                            class="pressable min-h-touch rounded-2xl border-2 px-4 text-lg font-bold"
                            :class="
                                roleMap[p.id] === 'grade'
                                    ? 'border-primary bg-primary-soft text-primary-ink'
                                    : 'border-line bg-surface text-ink hover:border-ink-soft'
                            "
                            :aria-pressed="roleMap[p.id] === 'grade'"
                            @click="setRole(p.id, 'grade')"
                        >
                            {{ roles.grade || 'Hasil sortir' }}
                        </button>
                        <button
                            type="button"
                            class="pressable min-h-touch rounded-2xl border-2 px-4 text-lg font-bold"
                            :class="
                                !roleMap[p.id]
                                    ? 'border-ink-soft bg-surface-2 text-ink'
                                    : 'border-line bg-surface text-ink-soft hover:border-ink-soft'
                            "
                            :aria-pressed="!roleMap[p.id]"
                            @click="roleMap[p.id] = null"
                        >
                            Bukan telur
                        </button>
                    </div>
                </li>
            </ul>

            <BigButton
                type="submit"
                size="large"
                block
                :loading="form.processing"
                :disabled="!mixedIds.length || !gradeIds.length"
            >
                Simpan jenis telur
            </BigButton>
        </form>
    </div>
</template>
