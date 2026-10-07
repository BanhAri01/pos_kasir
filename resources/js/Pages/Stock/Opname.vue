<script setup>
/**
 * Hitung stok: isi jumlah sebenarnya di rak. Hanya barang yang diisi yang disimpan.
 *
 * Barang curah yang karungnya SELALU SAMA beratnya (dikemas sendiri) boleh dihitung per karung + sisa curah.
 * Karung pemasok yang beratnya beda-beda WAJIB ditimbang: hitung "karung x rata-rata" bisa memunculkan
 * selisih palsu dan membuat karyawan dituduh curang.
 */
import { computed, reactive, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import QtyInput from '@/Components/ui/QtyInput.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import { useAuth } from '@/composables/useAuth';
import { fmtQty, num } from '@/composables/useQty';

defineOptions({ layout: AppLayout });

const props = defineProps({
    products: { type: Array, required: true },
});

const { hasModule } = useAuth();
const counted = reactive({});

// Per karung: { packs, loose } -> kg = karung x isi + sisa. mode: 'packs' | 'kg'
const packs = reactive({});
const loose = reactive({});
const mode = reactive({});
const byPack = (p) => hasModule('base_unit_stock') && num(p.pack_size) > 0 && p.pack_weight_fixed;
const mustWeigh = (p) => hasModule('base_unit_stock') && num(p.pack_size) > 0 && !p.pack_weight_fixed;
const modeOf = (p) => mode[p.id] ?? (byPack(p) ? 'packs' : 'kg');

function syncFromPacks(p) {
    const hasInput = String(packs[p.id] ?? '').trim() !== '' || String(loose[p.id] ?? '').trim() !== '';
    counted[p.id] = hasInput ? fmtQty(num(packs[p.id]) * num(p.pack_size) + num(loose[p.id])) : '';
}

const packLabel = (p) => p.pack_name || 'karung';
const inPacks = (kg, p) => fmtQty(Math.round((kg / num(p.pack_size)) * 10) / 10);
const search = ref('');
const confirm = ref(false);
const saving = ref(false);
const errors = ref({});

const visible = computed(() => {
    const term = search.value.trim().toLowerCase();
    return term ? props.products.filter((p) => p.name.toLowerCase().includes(term)) : props.products;
});

const filled = computed(() => props.products.filter((p) => counted[p.id] !== undefined && counted[p.id] !== ''));

function difference(product) {
    if (counted[product.id] === undefined || String(counted[product.id]).trim() === '') return null;
    const value = num(counted[product.id]);
    if (Number.isNaN(value)) return null;
    return Math.round((value - (product.stock_raw ?? 0)) * 1000) / 1000;
}

function save() {
    saving.value = true;
    router.post(
        route('stock.opname.store'),
        { items: filled.value.map((p) => ({ product_id: p.id, counted_qty: counted[p.id] })) },
        {
            onError: (e) => (errors.value = e),
            onFinish: () => {
                saving.value = false;
                confirm.value = false;
            },
        },
    );
}
</script>

<template>
    <Head title="Hitung Stok" />

    <div class="mx-auto max-w-3xl">
        <PageHeader title="Hitung Stok" subtitle="Isi jumlah barang yang Anda hitung di rak." :back-href="route('stock.index')" help="stock-opname" />

        <label class="relative mb-4 block">
            <span class="sr-only">Cari barang</span>
            <Search :size="24" class="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-ink-soft" aria-hidden="true" />
            <input
                v-model="search"
                type="search"
                placeholder="Cari barang"
                class="min-h-touch w-full rounded-2xl border-2 border-line bg-surface pr-4 pl-13 text-lg text-ink focus:border-focus focus:outline-none"
            />
        </label>

        <p v-if="errors.items" role="alert" class="mb-3 text-lg font-bold text-danger-ink">{{ errors.items }}</p>

        <ul class="flex flex-col gap-3 pb-28">
            <li v-for="product in visible" :key="product.id" class="card p-4">
                <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                    <p class="text-lg font-extrabold text-ink">{{ product.name }}</p>
                    <p class="text-base text-ink-soft">
                        Di aplikasi: <strong class="text-ink">{{ product.stock }} {{ product.unit }}</strong>
                        <template v-if="byPack(product)"> ≈ {{ product.pack_equivalent }} {{ packLabel(product) }}</template>
                    </p>
                </div>

                <template v-if="byPack(product)">
                    <SegmentedControl
                        class="mb-3"
                        :model-value="modeOf(product)"
                        :label="`Cara hitung ${product.name}`"
                        :options="[{ value: 'packs', label: `Hitung ${packLabel(product)}` }, { value: 'kg', label: 'Timbang (kg)' }]"
                        @update:model-value="(m) => { mode[product.id] = m; counted[product.id] = ''; packs[product.id] = ''; loose[product.id] = ''; }"
                    />
                    <div v-if="modeOf(product) === 'packs'" class="grid gap-3 sm:grid-cols-2">
                        <QtyInput v-model="packs[product.id]" :label="`Jumlah ${packLabel(product)} penuh`" :unit="packLabel(product)" compact @update:model-value="syncFromPacks(product)" />
                        <QtyInput v-model="loose[product.id]" :label="`Sisa curah ${product.name}`" :unit="product.unit" decimal compact @update:model-value="syncFromPacks(product)" />
                    </div>
                    <p v-if="modeOf(product) === 'packs'" class="mt-1 text-base text-ink-soft">
                        1 {{ packLabel(product) }} = {{ product.pack_size }} {{ product.unit }}. Sisa curah: isi kalau ada karung yang tidak penuh / jagung lepas (boleh kosong).
                        <template v-if="counted[product.id] !== undefined && counted[product.id] !== ''"><br />Total: <strong class="text-ink">{{ counted[product.id] }} {{ product.unit }}</strong></template>
                    </p>
                    <QtyInput v-else v-model="counted[product.id]" :label="`Hasil timbang ${product.name}`" compact :unit="product.unit" decimal />
                </template>
                <template v-else>
                    <QtyInput v-model="counted[product.id]" :label="mustWeigh(product) ? `Hasil timbang ${product.name}` : `Hasil hitung ${product.name}`" compact :unit="product.unit" :decimal="product.unit_allows_decimal" />
                    <p v-if="mustWeigh(product)" class="mt-1 text-base text-ink-soft">Berat karung dari pemasok beda-beda, jadi isi dengan <strong class="text-ink">hasil timbang</strong> (kg), bukan jumlah karung.</p>
                </template>
                <p v-if="difference(product) !== null" class="mt-2 text-base font-bold" :class="difference(product) === 0 ? 'text-primary-ink' : difference(product) < 0 ? 'text-danger-ink' : 'text-info-ink'">
                    {{ difference(product) === 0 ? 'Cocok' : difference(product) < 0 ? `Kurang ${String(-difference(product)).replace('.', ',')} ${product.unit}` : `Lebih ${String(difference(product)).replace('.', ',')} ${product.unit}` }}
                    <template v-if="byPack(product) && difference(product) !== 0"> (≈ {{ inPacks(Math.abs(difference(product)), product) }} {{ packLabel(product) }})</template>
                </p>
            </li>
        </ul>
    </div>

    <!-- Tombol simpan menempel di bawah, dekat jempol -->
    <div class="fixed inset-x-0 bottom-[calc(4.6rem+env(safe-area-inset-bottom))] z-20 border-t border-line bg-surface p-3 lg:bottom-0 lg:left-72">
        <div class="mx-auto max-w-3xl">
            <BigButton block size="large" :disabled="!filled.length" @click="confirm = true">
                Simpan Hasil Hitung ({{ filled.length }} barang)
            </BigButton>
        </div>
    </div>

    <ConfirmDialog
        v-model:open="confirm"
        title="Simpan hasil hitung?"
        :message="`Stok ${filled.length} barang akan disamakan dengan jumlah yang Anda hitung.`"
        confirm-text="Ya, Simpan"
        :loading="saving"
        @confirm="save"
    />
</template>
