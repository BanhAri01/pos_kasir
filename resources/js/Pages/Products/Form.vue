<script setup>
/**
 * Tambah / ubah barang. Yang wajib hanya nama & harga jual; pengaturan lanjutan
 * disembunyikan di "Pengaturan lainnya" supaya form tetap pendek.
 */
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ChevronDown, History, Plus, Trash2 } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import BigInput from '@/Components/ui/BigInput.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import ImagePicker from '@/Components/ui/ImagePicker.vue';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import ProductExtras from '@/Components/ProductExtras.vue';
import QtyInput from '@/Components/ui/QtyInput.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import ToggleSwitch from '@/Components/ui/ToggleSwitch.vue';
import { formatRupiah } from '@/composables/useRupiah';
import { useAuth } from '@/composables/useAuth';

defineOptions({ layout: AppLayout });

const props = defineProps({
    product: { type: Object, default: null },
    categories: { type: Array, required: true },
    units: { type: Array, required: true },
    extras: { type: Object, default: () => ({ recipe: [], units: [], prices: [], modifier_group_ids: [] }) },
    ingredients: { type: Array, default: () => [] },
    priceLevels: { type: Array, default: () => [] },
    modifierGroups: { type: Array, default: () => [] },
    consignors: { type: Array, default: () => [] },
    variantCount: { type: Number, default: 0 },
    parentName: { type: String, default: null },
});

const extrasRef = ref(null);

const { can, hasModule } = useAuth();
const isEdit = !!props.product;
const p = props.product ?? {};

const form = useForm({
    name: p.name ?? '',
    type: p.type ?? 'goods',
    category_id: p.category_id ?? null,
    new_category: '',
    price: p.price ?? null,
    cost_price: p.cost_price || null,
    base_unit_id: p.unit_id ?? props.units.find((u) => u.name === 'Pcs')?.id ?? null,
    track_stock: p.track_stock ?? true,
    initial_stock: '',
    min_stock: p.min_stock ?? '',
    pack_size: p.pack_size ?? '',
    pack_name: p.pack_name ?? 'karung',
    pack_weight_fixed: p.pack_weight_fixed ?? false,
    is_packaging: p.is_packaging ?? false,
    track_batch: p.track_batch ?? false,
    consignor_id: p.consignor_id ?? null,
    consignment_share: p.consignment_share ?? '',
    pricing_mode: p.pricing_mode ?? 'fixed',
    duration_minutes: p.duration_minutes ?? null,
    code: p.code ?? '',
    barcode: p.barcode ?? '',
    description: p.description ?? '',
    image: null,
    remove_image: false,
    recipe: props.extras.recipe.map((r) => ({ ...r })),
    units: props.extras.units.map((u) => ({ ...u })),
    prices: props.extras.prices.map((r) => ({ ...r })),
    modifier_group_ids: [...props.extras.modifier_group_ids],
});

const showAdvanced = ref(!!(p.code || p.barcode || p.description || p.pricing_mode !== 'fixed'));
const addingCategory = ref(false);
const confirmDelete = ref(false);
const soldOut = ref(p.sold_out_today ?? false);

const selectedUnit = computed(() => props.units.find((u) => u.id === form.base_unit_id));
const profit = computed(() => (form.price && form.cost_price ? form.price - form.cost_price : null));
const isService = computed(() => form.type === 'service');

const typeOptions = computed(() => [
    { value: 'goods', label: 'Barang' },
    { value: 'service', label: 'Layanan / Jasa' },
    ...(hasModule('recipe') || hasModule('production') || hasModule('packaging_materials') || p.type === 'ingredient' ? [{ value: 'ingredient', label: 'Bahan Baku' }] : []),
]);

function chooseCategory(id) {
    form.category_id = id;
    form.new_category = '';
    addingCategory.value = false;
}

function submit() {
    const options = { forceFormData: true, preserveScroll: true };
    const extras = extrasRef.value?.visibleSections ?? [];
    // Bagian yang tidak tampil (modul mati) tidak dikirim, supaya datanya tidak terhapus.
    const withExtras = (data) => {
        const payload = { ...data, extras };
        for (const key of ['recipe', 'units', 'prices', 'modifier_group_ids']) {
            if (!extras.includes(key)) delete payload[key];
        }
        return payload;
    };
    if (isEdit) {
        form.transform((data) => ({ ...withExtras(data), _method: 'put' })).post(route('products.update', p.id), options);
    } else {
        form.transform(withExtras).post(route('products.store'), options);
    }
}

function toggleSoldOut(value) {
    soldOut.value = value;
    router.put(route('products.sold-out', p.id), { sold_out: value }, { preserveScroll: true, preserveState: true });
}

const chip = (active) => [
    'pressable flex min-h-12 items-center gap-1 rounded-full border-2 px-4 text-base font-bold',
    active ? 'border-primary bg-primary-soft text-primary-ink' : 'border-line bg-surface text-ink hover:border-ink-soft',
];
</script>

<template>
    <Head :title="isEdit ? `Ubah ${p.name}` : 'Tambah Barang'" />

    <div class="mx-auto max-w-2xl">
        <PageHeader :title="isEdit ? 'Ubah Barang' : 'Tambah Barang'" :back-href="route('products.index')" help="product-form" />

        <form class="flex flex-col gap-4" @submit.prevent="submit">
            <section class="card flex flex-col gap-5 p-5 sm:p-6">
                <SegmentedControl v-model="form.type" label="Jenis" :options="typeOptions" />

                <BigInput
                    v-model="form.name"
                    :label="isService ? 'Nama layanan' : 'Nama barang'"
                    :placeholder="isService ? 'Contoh: Potong Rambut' : 'Contoh: Kopi Susu'"
                    :error="form.errors.name"
                />

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <MoneyInput v-model="form.price" label="Harga jual" :error="form.errors.price" />
                    <MoneyInput
                        v-model="form.cost_price"
                        label="Modal"
                        optional
                        :hint="profit !== null ? `Untung ${formatRupiah(profit)} per ${selectedUnit?.name?.toLowerCase() ?? 'barang'}` : 'Harga beli Anda, untuk hitung untung.'"
                        :error="form.errors.cost_price"
                    />
                </div>

                <!-- Kategori -->
                <fieldset>
                    <legend class="mb-2 text-lg font-bold text-ink">Kategori</legend>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" :class="chip(!form.category_id && !form.new_category)" @click="chooseCategory(null)">Tanpa kategori</button>
                        <button v-for="c in categories" :key="c.id" type="button" :class="chip(form.category_id === c.id)" @click="chooseCategory(c.id)">
                            {{ c.name }}
                        </button>
                        <button v-if="!addingCategory" type="button" :class="chip(false)" @click="addingCategory = true; form.category_id = null">
                            <Plus :size="18" aria-hidden="true" /> Kategori baru
                        </button>
                    </div>
                    <BigInput v-if="addingCategory" v-model="form.new_category" label="Nama kategori baru" placeholder="Contoh: Minuman" class="mt-3" />
                </fieldset>

                <ImagePicker
                    :current-url="p.image_url"
                    :error="form.errors.image"
                    @change="(file) => { form.image = file; form.remove_image = false; }"
                    @remove="() => { form.image = null; form.remove_image = true; }"
                />
            </section>

            <!-- Stok -->
            <section v-if="!isService" class="card flex flex-col gap-5 p-5 sm:p-6">
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                    <div class="min-w-48 flex-1">
                        <h2 class="text-xl font-extrabold text-ink">Hitung stok</h2>
                        <p class="text-base text-ink-soft">Stok berkurang otomatis setiap kali terjual.</p>
                    </div>
                    <ToggleSwitch v-model="form.track_stock" label="Hitung stok" class="ml-auto" />
                </div>

                <label class="block">
                    <span class="mb-2 block text-lg font-bold text-ink">Satuan</span>
                    <select
                        v-model="form.base_unit_id"
                        class="min-h-touch w-full rounded-2xl border-2 border-line bg-surface px-4 text-lg text-ink focus:border-focus focus:outline-none"
                    >
                        <option v-for="u in units" :key="u.id" :value="u.id">{{ u.name }}</option>
                    </select>
                </label>

                <template v-if="form.track_stock">
                    <div v-if="isEdit" class="rounded-2xl bg-surface-2 p-4">
                        <p class="text-lg text-ink-soft">Stok sekarang di outlet ini</p>
                        <p class="font-display text-3xl font-extrabold text-ink">{{ p.stock ?? 0 }} {{ selectedUnit?.name }}</p>
                        <p class="mt-1 text-base text-ink-soft">Untuk menambah atau mengurangi stok, pakai menu Stok Masuk / Stok Keluar.</p>
                        <BigButton v-if="can('manage_stock')" :href="route('stock.history', p.id)" variant="ghost" class="mt-2 -ml-3">
                            <History :size="22" aria-hidden="true" /> Lihat riwayat stok
                        </BigButton>
                    </div>
                    <QtyInput
                        v-else
                        v-model="form.initial_stock"
                        label="Stok awal (yang ada sekarang)"
                        :unit="selectedUnit?.name"
                        :decimal="selectedUnit?.allow_decimal"
                        :error="form.errors.initial_stock"
                    />
                    <QtyInput
                        v-model="form.min_stock"
                        label="Beri tanda kalau stok tinggal"
                        :unit="selectedUnit?.name"
                        :decimal="selectedUnit?.allow_decimal"
                        :error="form.errors.min_stock"
                    />
                    <!-- Barang curah: isi 1 karung, untuk tampilan "setara karung" -->
                    <div v-if="hasModule('base_unit_stock') && selectedUnit?.allow_decimal" class="grid gap-4 sm:grid-cols-2">
                        <QtyInput
                            v-model="form.pack_size"
                            :label="`Isi 1 ${form.pack_name || 'karung'}`"
                            :unit="selectedUnit?.name"
                            decimal
                            :error="form.errors.pack_size"
                        />
                        <BigInput
                            v-model="form.pack_name"
                            label="Nama kemasan"
                            optional
                            placeholder="karung"
                            hint="Stok tetap dihitung per kg dari timbangan. Isi 1 karung hanya untuk perkiraan &quot;setara karung&quot; dan hitung stok per karung. Kalau berat karung dari pemasok beda-beda, isi rata-ratanya (contoh: 48,6)."
                            :error="form.errors.pack_name"
                        />
                        <div v-if="form.pack_size" class="flex flex-wrap items-center gap-x-4 gap-y-2 sm:col-span-2">
                            <span class="min-w-48 flex-1">
                                <span class="block text-lg text-ink">Berat setiap {{ form.pack_name || 'karung' }} selalu sama</span>
                                <span class="block text-base text-ink-soft">
                                    Nyalakan untuk karung yang Anda kemas sendiri dengan berat tetap: hitung stok boleh per karung.
                                    Matikan untuk karung dari pemasok yang beratnya beda-beda: hitung stok wajib ditimbang.
                                </span>
                            </span>
                            <ToggleSwitch v-model="form.pack_weight_fixed" :label="`Berat setiap ${form.pack_name || 'karung'} selalu sama`" class="ml-auto" />
                        </div>
                    </div>
                    <div v-if="form.type === 'ingredient' && (hasModule('packaging_materials') || hasModule('repack'))" class="flex flex-wrap items-center gap-x-4 gap-y-2">
                        <span class="min-w-48 flex-1">
                            <span class="block text-lg text-ink">Ini bahan kemas</span>
                            <span class="block text-base text-ink-soft">Karung kosong, benang jahit, label. Berkurang otomatis saat mengemas.</span>
                        </span>
                        <ToggleSwitch v-model="form.is_packaging" label="Ini bahan kemas" class="ml-auto" />
                    </div>
                    <div v-if="hasModule('batch_lot')" class="flex flex-wrap items-center gap-x-4 gap-y-2">
                        <span class="min-w-48 flex-1">
                            <span class="block text-lg text-ink">Catat nomor batch & kedaluwarsa</span>
                            <span class="block text-base text-ink-soft">Barang yang kedaluwarsa duluan otomatis keluar duluan.</span>
                        </span>
                        <ToggleSwitch v-model="form.track_batch" label="Catat nomor batch" class="ml-auto" />
                    </div>
                </template>
            </section>

            <ProductExtras
                ref="extrasRef"
                :form="form"
                :units="units"
                :ingredients="ingredients"
                :price-levels="priceLevels"
                :modifier-groups="modifierGroups"
                :is-service="isService"
            />

            <!-- Ukuran & warna (toko baju) -->
            <section v-if="hasModule('variant_matrix') && form.type === 'goods' && (parentName || isEdit)" class="card flex flex-wrap items-center gap-x-4 gap-y-3 p-5 sm:p-6">
                <div class="min-w-48 flex-1">
                    <h2 class="text-xl font-extrabold text-ink">Ukuran & warna</h2>
                    <p v-if="parentName" class="text-base text-ink-soft">Ini salah satu varian dari <strong>{{ parentName }}</strong>.</p>
                    <p v-else-if="variantCount" class="text-base text-ink-soft">{{ variantCount }} varian. Stok, harga, dan barcode diatur per varian.</p>
                    <p v-else class="text-base text-ink-soft">Punya beberapa ukuran atau warna? Buat semua kombinasinya sekaligus.</p>
                </div>
                <BigButton v-if="parentName" :href="route('products.variants.edit', p.parent_id)" variant="secondary">Lihat semua varian</BigButton>
                <BigButton v-else :href="route('products.variants.edit', p.id)" variant="secondary">{{ variantCount ? 'Atur Ukuran & Warna' : 'Tambah Ukuran & Warna' }}</BigButton>
            </section>

            <!-- Barang titipan (konsinyasi) -->
            <section v-if="hasModule('consignment') && form.type === 'goods'" class="card flex flex-col gap-4 p-5 sm:p-6">
                <div>
                    <h2 class="text-xl font-extrabold text-ink">Barang titipan</h2>
                    <p class="text-base text-ink-soft">Kalau barang ini titipan orang lain, pilih pemiliknya. Hasil penjualan dibagi sesuai bagian toko.</p>
                </div>
                <label class="block">
                    <span class="mb-2 block text-lg font-bold text-ink">Pemilik barang</span>
                    <select v-model="form.consignor_id" class="min-h-touch w-full rounded-2xl border-2 border-line bg-surface px-4 text-lg text-ink">
                        <option :value="null">Bukan barang titipan</option>
                        <option v-for="c in consignors" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </label>
                <BigInput v-if="form.consignor_id" v-model="form.consignment_share" label="Bagian toko (%)" inputmode="decimal" placeholder="Contoh: 20" hint="Sisanya dibayarkan ke pemilik barang." :error="form.errors.consignment_share" />
            </section>

            <!-- Habis hari ini -->
            <section v-if="isEdit" class="card flex flex-wrap items-center gap-x-4 gap-y-2 p-5 sm:p-6">
                <div class="min-w-48 flex-1">
                    <h2 class="text-xl font-extrabold text-ink">Habis hari ini</h2>
                    <p class="text-base text-ink-soft">Sembunyikan dari kasir untuk hari ini saja. Besok otomatis tersedia lagi.</p>
                </div>
                <ToggleSwitch :model-value="soldOut" label="Habis hari ini" class="ml-auto" @update:model-value="toggleSoldOut" />
            </section>

            <!-- Pengaturan lainnya -->
            <section class="card p-5 sm:p-6">
                <button
                    type="button"
                    class="flex min-h-12 w-full items-center justify-between text-left text-xl font-extrabold text-ink"
                    :aria-expanded="showAdvanced"
                    @click="showAdvanced = !showAdvanced"
                >
                    Pengaturan lainnya
                    <ChevronDown :size="26" class="transition-transform" :class="showAdvanced ? 'rotate-180' : ''" aria-hidden="true" />
                </button>

                <div v-if="showAdvanced" class="mt-4 flex flex-col gap-5">
                    <fieldset>
                        <legend class="mb-2 text-lg font-bold text-ink">Cara menentukan harga</legend>
                        <SegmentedControl
                            v-model="form.pricing_mode"
                            label="Cara menentukan harga"
                            :options="[
                                { value: 'fixed', label: 'Harga tetap' },
                                { value: 'per_weight', label: 'Per berat / ukuran' },
                                { value: 'open_price', label: 'Diisi saat jual' },
                            ]"
                        />
                    </fieldset>
                    <BigInput
                        v-if="isService"
                        v-model="form.duration_minutes"
                        label="Lama layanan (menit)"
                        inputmode="numeric"
                        optional
                        placeholder="Contoh: 30"
                        :error="form.errors.duration_minutes"
                    />
                    <BigInput v-model="form.code" label="Kode barang" optional placeholder="Contoh: KP-001" :error="form.errors.code" />
                    <BigInput
                        v-if="hasModule('barcode') || form.barcode"
                        v-model="form.barcode"
                        label="Barcode"
                        optional
                        inputmode="numeric"
                        hint="Scan dengan alat scanner, atau ketik angkanya."
                        :error="form.errors.barcode"
                    />
                    <BigInput v-model="form.description" label="Keterangan" optional :error="form.errors.description" />
                </div>
            </section>

            <BigButton type="submit" size="large" block :loading="form.processing">Simpan</BigButton>

            <BigButton v-if="isEdit" variant="ghost" block class="text-danger-ink" @click="confirmDelete = true">
                <Trash2 :size="22" aria-hidden="true" /> Hapus barang ini
            </BigButton>
        </form>
    </div>

    <ConfirmDialog
        v-if="isEdit"
        v-model:open="confirmDelete"
        :title="`Hapus ${p.name}?`"
        message="Barang tidak akan tampil di kasir. Riwayat penjualannya tetap tersimpan, dan Anda bisa membatalkan sesaat setelah menghapus."
        confirm-text="Ya, Hapus"
        danger
        @confirm="router.delete(route('products.destroy', p.id))"
    />
</template>
