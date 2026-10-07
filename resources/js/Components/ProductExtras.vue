<script setup>
/**
 * Bagian tambahan formulir barang, hanya muncul kalau modulnya aktif:
 *   - Resep (bahan baku per 1 menu → stok bahan berkurang & modal dihitung otomatis)
 *   - Satuan lain (1 dus = 40 pcs, harga per dus)
 *   - Harga grosir / tipe harga (beli ≥ 12 harga lebih murah, atau harga khusus tukang)
 *   - Pilihan & tambahan (ukuran, gula, topping)
 *
 * `form` adalah objek useForm milik halaman induk; bagian ini mengisi
 * form.recipe / form.units / form.prices / form.modifier_group_ids.
 */
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ChefHat, Layers, Plus, SlidersHorizontal, Tags, X } from 'lucide-vue-next';
import BigButton from '@/Components/ui/BigButton.vue';
import BigInput from '@/Components/ui/BigInput.vue';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import { useAuth } from '@/composables/useAuth';
import { formatRupiah } from '@/composables/useRupiah';

const props = defineProps({
    form: { type: Object, required: true },
    units: { type: Array, required: true },
    ingredients: { type: Array, default: () => [] },
    priceLevels: { type: Array, default: () => [] },
    modifierGroups: { type: Array, default: () => [] },
    isService: { type: Boolean, default: false },
});

const { hasModule } = useAuth();
const ingredientQuery = ref('');

const showRecipe = computed(() => hasModule('recipe') && props.form.type !== 'ingredient');
const showUnits = computed(() => hasModule('multi_unit') && !props.isService && props.form.type !== 'ingredient');
const showPrices = computed(() => hasModule('price_levels') && props.form.type !== 'ingredient');
const showModifiers = computed(() => hasModule('variants_modifiers') && props.form.type !== 'ingredient');

const baseUnitName = computed(() => props.units.find((u) => u.id === props.form.base_unit_id)?.name ?? 'pcs');
const unitName = (id) => props.units.find((u) => u.id === id)?.name ?? '';
const ingredientById = (id) => props.ingredients.find((i) => i.id === id);

const ingredientMatches = computed(() => {
    const q = ingredientQuery.value.trim().toLowerCase();
    if (!q) return [];
    const chosen = props.form.recipe.map((r) => r.ingredient_id);
    return props.ingredients.filter((i) => i.name.toLowerCase().includes(q) && !chosen.includes(i.id)).slice(0, 6);
});

// Modal per menu dari resep = jumlah bahan × modal bahan.
const recipeCost = computed(() =>
    props.form.recipe.reduce((sum, r) => sum + Math.round((parseFloat(String(r.qty).replace(',', '.')) || 0) * (ingredientById(r.ingredient_id)?.cost_price || 0)), 0),
);

function addIngredient(item) {
    props.form.recipe.push({ ingredient_id: item.id, qty: '1' });
    ingredientQuery.value = '';
}

const unitChoices = computed(() => [{ id: null, name: baseUnitName.value }, ...props.form.units.filter((u) => u.unit_id).map((u) => ({ id: u.unit_id, name: unitName(u.unit_id) }))]);

function toggleGroup(id) {
    const list = props.form.modifier_group_ids;
    const i = list.indexOf(id);
    i === -1 ? list.push(id) : list.splice(i, 1);
}

const select = 'min-h-touch w-full rounded-2xl border-2 border-line bg-surface px-4 text-lg text-ink focus:border-focus focus:outline-none';
const removeBtn = 'pressable mb-1 flex size-touch shrink-0 items-center justify-center rounded-xl text-danger-ink';

/** Daftar bagian yang tampil (dikirim ke server). */
const visibleSections = computed(() => [
    ...(showRecipe.value ? ['recipe'] : []),
    ...(showUnits.value ? ['units'] : []),
    ...(showPrices.value ? ['prices'] : []),
    ...(showModifiers.value ? ['modifier_group_ids'] : []),
]);
defineExpose({ visibleSections });
</script>

<template>
    <!-- Resep -->
    <section v-if="showRecipe" class="card flex flex-col gap-4 p-5 sm:p-6">
        <div>
            <h2 class="flex items-center gap-2 text-xl font-extrabold text-ink"><ChefHat :size="24" aria-hidden="true" /> Resep / bahan</h2>
            <p class="text-base text-ink-soft">Bahan yang terpakai untuk 1 {{ isService ? 'layanan' : baseUnitName.toLowerCase() }}. Stok bahan berkurang otomatis saat terjual.</p>
        </div>
        <div class="relative">
            <input v-model="ingredientQuery" type="search" placeholder="Cari bahan, mis. susu, gula" :class="select" aria-label="Cari bahan" />
            <ul v-if="ingredientMatches.length" class="absolute inset-x-0 top-full z-10 mt-1 overflow-hidden rounded-2xl border-2 border-line bg-surface shadow-float">
                <li v-for="i in ingredientMatches" :key="i.id">
                    <button type="button" class="flex min-h-touch w-full items-center justify-between gap-3 px-4 text-left text-lg hover:bg-surface-2" @click="addIngredient(i)">
                        <span class="min-w-0 truncate font-bold text-ink">{{ i.name }}</span>
                        <span class="shrink-0 text-base text-ink-soft">{{ i.unit }}</span>
                    </button>
                </li>
            </ul>
            <p v-if="!ingredients.length" class="mt-2 text-base text-ink-soft">Belum ada bahan baku. Tambahkan barang dengan jenis “Bahan Baku” dulu.</p>
        </div>
        <ul v-if="form.recipe.length" class="flex flex-col gap-3">
            <li v-for="(r, i) in form.recipe" :key="r.ingredient_id" class="grid grid-cols-[minmax(0,1fr)_minmax(0,8rem)_auto] items-end gap-2">
                <p class="min-w-0 self-center truncate text-lg font-bold text-ink">{{ ingredientById(r.ingredient_id)?.name ?? r.name }}</p>
                <BigInput v-model="r.qty" :label="ingredientById(r.ingredient_id)?.unit || 'Jumlah'" inputmode="decimal" :error="form.errors[`recipe.${i}.qty`]" />
                <button type="button" :class="removeBtn" :aria-label="`Hapus bahan ${ingredientById(r.ingredient_id)?.name ?? ''}`" @click="form.recipe.splice(i, 1)"><X :size="24" /></button>
            </li>
        </ul>
        <p v-if="form.recipe.length && recipeCost" class="rounded-2xl bg-surface-2 p-3 text-lg text-ink">Perkiraan modal dari resep: <strong>{{ formatRupiah(recipeCost) }}</strong></p>
    </section>

    <!-- Satuan lain -->
    <section v-if="showUnits" class="card flex flex-col gap-4 p-5 sm:p-6">
        <div>
            <h2 class="flex items-center gap-2 text-xl font-extrabold text-ink"><Layers :size="24" aria-hidden="true" /> Jual per satuan lain</h2>
            <p class="text-base text-ink-soft">Contoh: 1 Dus isi 40 {{ baseUnitName }}. Stok tetap dihitung dalam {{ baseUnitName }}.</p>
        </div>
        <ul class="flex flex-col gap-4">
            <li v-for="(u, i) in form.units" :key="i" class="grid grid-cols-2 items-end gap-2 rounded-2xl bg-surface-2 p-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,7rem)_minmax(0,10rem)_auto]">
                <label class="block">
                    <span class="mb-2 block text-lg font-bold text-ink">Satuan</span>
                    <select v-model="u.unit_id" :class="select">
                        <option :value="null" disabled>Pilih…</option>
                        <option v-for="unit in units.filter((x) => x.id !== form.base_unit_id)" :key="unit.id" :value="unit.id">{{ unit.name }}</option>
                    </select>
                    <span v-if="form.errors[`units.${i}.unit_id`]" class="mt-1 block text-base font-bold text-danger-ink">{{ form.errors[`units.${i}.unit_id`] }}</span>
                </label>
                <BigInput v-model="u.conversion_qty" :label="`Isi (${baseUnitName})`" inputmode="decimal" :error="form.errors[`units.${i}.conversion_qty`]" />
                <MoneyInput v-model="u.price" :label="`Harga per ${unitName(u.unit_id) || 'satuan'}`" :error="form.errors[`units.${i}.price`]" />
                <button type="button" :class="removeBtn" aria-label="Hapus satuan" @click="form.units.splice(i, 1)"><X :size="24" /></button>
                <BigInput v-if="hasModule('barcode')" v-model="u.barcode" label="Barcode satuan ini" optional inputmode="numeric" class="col-span-2 sm:col-span-4" />
            </li>
        </ul>
        <BigButton variant="ghost" class="self-start" @click="form.units.push({ id: null, unit_id: null, conversion_qty: '', price: null, barcode: '' })">
            <Plus :size="22" aria-hidden="true" /> Tambah Satuan
        </BigButton>
    </section>

    <!-- Harga grosir / tipe harga -->
    <section v-if="showPrices" class="card flex flex-col gap-4 p-5 sm:p-6">
        <div>
            <h2 class="flex items-center gap-2 text-xl font-extrabold text-ink"><Tags :size="24" aria-hidden="true" /> Harga grosir & harga khusus</h2>
            <p class="text-base text-ink-soft">Harga otomatis turun kalau beli banyak, atau untuk pelanggan tipe tertentu.</p>
        </div>
        <ul class="flex flex-col gap-4">
            <li v-for="(row, i) in form.prices" :key="i" class="grid grid-cols-2 items-end gap-2 rounded-2xl bg-surface-2 p-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,6rem)_minmax(0,9rem)_auto]">
                <label class="block">
                    <span class="mb-2 block text-lg font-bold text-ink">Untuk</span>
                    <select v-model="row.price_level_id" :class="select">
                        <option :value="null">Semua pelanggan</option>
                        <option v-for="l in priceLevels" :key="l.id" :value="l.id">{{ l.name }}</option>
                    </select>
                </label>
                <label class="block">
                    <span class="mb-2 block text-lg font-bold text-ink">Satuan</span>
                    <select v-model="row.unit_id" :class="select">
                        <option v-for="c in unitChoices" :key="String(c.id)" :value="c.id">{{ c.name }}</option>
                    </select>
                </label>
                <BigInput v-model="row.min_qty" label="Min. beli" inputmode="decimal" :error="form.errors[`prices.${i}.min_qty`]" />
                <MoneyInput v-model="row.price" label="Harga" :error="form.errors[`prices.${i}.price`]" />
                <button type="button" :class="removeBtn" aria-label="Hapus harga" @click="form.prices.splice(i, 1)"><X :size="24" /></button>
            </li>
        </ul>
        <BigButton variant="ghost" class="self-start" @click="form.prices.push({ price_level_id: null, unit_id: null, min_qty: '12', price: null })">
            <Plus :size="22" aria-hidden="true" /> Tambah Harga
        </BigButton>
        <p v-if="!priceLevels.length" class="text-base text-ink-soft">
            Mau harga khusus untuk tukang / reseller? Buat dulu di <Link :href="route('price-levels.index')" class="font-bold text-primary-ink underline">Tipe Harga</Link>.
        </p>
    </section>

    <!-- Pilihan & tambahan -->
    <section v-if="showModifiers" class="card flex flex-col gap-4 p-5 sm:p-6">
        <div>
            <h2 class="flex items-center gap-2 text-xl font-extrabold text-ink"><SlidersHorizontal :size="24" aria-hidden="true" /> Pilihan & tambahan</h2>
            <p class="text-base text-ink-soft">Kasir akan diminta memilih saat menambahkan {{ form.name || 'menu ini' }}.</p>
        </div>
        <div v-if="modifierGroups.length" class="flex flex-wrap gap-2">
            <button
                v-for="g in modifierGroups"
                :key="g.id"
                type="button"
                class="pressable min-h-touch rounded-2xl border-2 px-4 text-left text-lg font-bold"
                :class="form.modifier_group_ids.includes(g.id) ? 'border-primary bg-primary-soft text-primary-ink' : 'border-line text-ink'"
                :aria-pressed="form.modifier_group_ids.includes(g.id)"
                @click="toggleGroup(g.id)"
            >
                {{ g.name }}
                <span class="block text-sm font-normal text-ink-soft">{{ g.options.map((o) => o.name).join(', ') }}</span>
            </button>
        </div>
        <p v-else class="text-base text-ink-soft">
            Belum ada pilihan. Buat di <Link :href="route('modifiers.index')" class="font-bold text-primary-ink underline">Pilihan & Tambahan</Link>.
        </p>
    </section>
</template>
