<script setup>
/**
 * Ubah / tambah satu pesanan:
 *  - jumlah atau berat, satuan (dus/pcs, sak, meter), pilihan ukuran & tambahan,
 *  - siapa yang mengerjakan (kapster/terapis, untuk komisi),
 *  - catatan ("pedas", "tanpa sayur"), harga & diskon (kalau diizinkan).
 */
import { computed, ref, watch } from 'vue';
import { Ban, Trash2 } from 'lucide-vue-next';
import BigButton from '@/Components/ui/BigButton.vue';
import BottomSheet from '@/Components/ui/BottomSheet.vue';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import QtyInput from '@/Components/ui/QtyInput.vue';
import { formatRupiah } from '@/composables/useRupiah';
import { addToCart, can, hasModule, markSoldOut, removeLine, simpleMode, store, updateLine } from '../store';
import { lineMoney } from '../lib/calculator';
import { missingRequiredGroup, resolvePrice, todayIn } from '../lib/pricing';
import { showToast } from '../lib/toast';

const open = defineModel('open', { type: Boolean, default: false });

const props = defineProps({
    line: { type: Object, default: null }, // mode ubah
    product: { type: Object, default: null }, // mode tambah
});

const qty = ref('1');
const price = ref(0);
const priceTouched = ref(false);
const discount = ref(null);
const note = ref('');
const unitId = ref(null);
const modifierIds = ref([]);
const staffId = ref(null);

const isAdd = computed(() => !!props.product && !props.line);
const product = computed(() => (props.product ?? store.boot.products.find((p) => p.id === props.line?.product_id)) || null);
const pricingMode = computed(() => product.value?.pricing_mode ?? 'fixed');
const decimal = computed(() => product.value?.unit_allows_decimal || pricingMode.value === 'per_weight');
const canEditPrice = computed(() => pricingMode.value === 'open_price' || (can('change_price') && !simpleMode()));
const canDiscount = computed(() => can('give_discount') && !simpleMode());
const units = computed(() => product.value?.units ?? []);
const groups = computed(() => product.value?.modifier_groups ?? []);
const showStaff = computed(() => (hasModule('staff_commission') || store.boot.tenant.pos_layout === 'service') && product.value?.type === 'service' && store.boot.staff.length > 0);

const quickNotes = computed(() =>
    store.boot.tenant.pos_layout === 'fnb' ? ['Pedas', 'Tidak pedas', 'Tanpa sayur', 'Es sedikit', 'Gula sedikit', 'Dibungkus'] : [],
);

const numericQty = computed(() => parseFloat(String(qty.value).replace(',', '.')) || 0);

/** Harga otomatis dari satuan + harga khusus + pilihan (sama seperti server). */
const autoPrice = computed(() =>
    product.value
        ? resolvePrice(product.value, { qty: numericQty.value || 1, unitId: unitId.value, modifierIds: modifierIds.value, priceLevelId: store.customer?.price_level_id ?? null, promotions: store.boot.promotions ?? [], date: todayIn(store.boot.tenant.timezone) })
        : null,
);

watch(autoPrice, (p) => {
    if (p && !priceTouched.value && pricingMode.value !== 'open_price') price.value = p.unitPrice;
});

const lineTotal = computed(() => Math.max(0, lineMoney(price.value ?? 0, numericQty.value) - (discount.value ?? 0)));
const unitLabel = computed(() => autoPrice.value?.unitName ?? product.value?.unit);

watch(open, (value) => {
    if (!value || !product.value) return;
    const l = props.line;
    qty.value = l ? String(l.qty).replace('.', ',') : decimal.value ? '' : '1';
    unitId.value = l?.unit_id ?? null;
    modifierIds.value = l ? [...l.modifier_ids] : [];
    staffId.value = l?.staff_id ?? null;
    discount.value = l?.discount_amount || null;
    note.value = l?.note ?? '';
    priceTouched.value = !!l?.price_overridden;
    price.value = l ? l.unit_price : pricingMode.value === 'open_price' ? null : product.value.price;
});

function toggleOption(group, option) {
    const inGroup = group.options.map((o) => o.id);
    if (group.selection === 'single') {
        const selected = modifierIds.value.includes(option.id);
        modifierIds.value = [...modifierIds.value.filter((id) => !inGroup.includes(id)), ...(selected && !group.is_required ? [] : [option.id])];
    } else {
        modifierIds.value = modifierIds.value.includes(option.id) ? modifierIds.value.filter((id) => id !== option.id) : [...modifierIds.value, option.id];
    }
}

function toggleNote(text) {
    const parts = note.value ? note.value.split(', ') : [];
    note.value = (parts.includes(text) ? parts.filter((p) => p !== text) : [...parts, text]).join(', ');
}

function save() {
    const q = numericQty.value;
    if (!q || q <= 0) {
        showToast(decimal.value ? `Isi ${pricingMode.value === 'per_weight' ? 'beratnya' : 'jumlahnya'} dulu.` : 'Jumlah harus lebih dari 0.', 'error');
        return;
    }
    if (pricingMode.value === 'open_price' && !price.value) {
        showToast('Isi harganya dulu.', 'error');
        return;
    }
    const missing = missingRequiredGroup(product.value, modifierIds.value);
    if (missing) {
        showToast(`Pilih ${missing.name} dulu.`, 'error');
        return;
    }

    const manualPrice = priceTouched.value || pricingMode.value === 'open_price' ? price.value ?? 0 : null;

    if (isAdd.value) {
        const line = addToCart(product.value, { qty: q, unitPrice: manualPrice, note: note.value.trim(), unitId: unitId.value, modifierIds: modifierIds.value, staffId: staffId.value });
        line.discount_amount = Math.min(discount.value ?? 0, lineMoney(line.unit_price, line.qty));
    } else {
        const changes = { qty: Math.round(q * 1000) / 1000, unit_id: unitId.value, modifier_ids: [...modifierIds.value], staff_id: staffId.value, note: note.value.trim() };
        if (manualPrice !== null) {
            changes.unit_price = manualPrice;
            changes.price_overridden = pricingMode.value !== 'open_price';
        } else {
            changes.price_overridden = false;
        }
        updateLine(props.line, changes);
        props.line.discount_amount = Math.min(discount.value ?? 0, lineMoney(props.line.unit_price, props.line.qty));
    }
    open.value = false;
}

function remove() {
    removeLine(props.line);
    open.value = false;
}

async function soldOut() {
    try {
        await markSoldOut(product.value, true);
        removeLine(props.line);
        open.value = false;
        showToast(`${product.value.name} ditandai habis untuk hari ini.`);
    } catch (e) {
        showToast(e.message, 'error');
    }
}

/** Tombol cepat berat umum (jual per kg). Isi 1 karung ikut ditampilkan kalau ada. */
const quickWeights = computed(() => {
    if (pricingMode.value !== 'per_weight') return [];
    const pack = parseFloat(String(product.value?.pack_size ?? '').replace(',', '.'));
    const list = [0.5, 1, 2, 5, 10, 25];
    if (pack && !list.includes(pack)) list.push(pack);
    return list.sort((a, b) => a - b);
});
const weightLabel = (w) => (w === 0.5 ? '½' : String(w).replace('.', ','));

const chip = (active) => [
    'pressable min-h-12 rounded-2xl border-2 px-4 text-base font-bold',
    active ? 'border-primary bg-primary-soft text-primary-ink' : 'border-line text-ink',
];
</script>

<template>
    <BottomSheet v-model:open="open" :title="product?.name ?? ''">
        <div v-if="product" class="flex flex-col gap-5">
            <!-- Satuan (multi_unit) -->
            <div v-if="units.length">
                <p class="mb-2 text-lg font-bold text-ink">Satuan</p>
                <div class="flex flex-wrap gap-2">
                    <button type="button" :class="chip(!unitId)" @click="unitId = null">{{ product.unit ?? 'Satuan dasar' }} · {{ formatRupiah(product.price) }}</button>
                    <button v-for="u in units" :key="u.id" type="button" :class="chip(unitId === u.id)" @click="unitId = u.id">
                        {{ u.name }} ({{ String(u.conversion).replace('.', ',') }} {{ product.unit }}) · {{ formatRupiah(u.price) }}
                    </button>
                </div>
            </div>

            <!-- Pilihan ukuran & tambahan -->
            <div v-for="group in groups" :key="group.id">
                <p class="mb-2 text-lg font-bold text-ink">
                    {{ group.name }}
                    <span class="font-semibold text-ink-soft">{{ group.is_required ? '(wajib pilih)' : group.selection === 'multiple' ? '(boleh lebih dari satu)' : '(boleh dikosongkan)' }}</span>
                </p>
                <div class="flex flex-wrap gap-2">
                    <button v-for="o in group.options" :key="o.id" type="button" :class="chip(modifierIds.includes(o.id))" @click="toggleOption(group, o)">
                        {{ o.name }}<template v-if="o.price_delta"> +{{ formatRupiah(o.price_delta) }}</template>
                    </button>
                </div>
            </div>

            <QtyInput v-model="qty" :label="pricingMode === 'per_weight' ? 'Berat' : 'Jumlah'" :unit="unitLabel" :decimal="decimal" :step="decimal ? 0.5 : 1" />
            <div v-if="quickWeights.length" class="-mt-2 flex flex-wrap gap-2" role="group" aria-label="Berat cepat">
                <button v-for="w in quickWeights" :key="w" type="button" :class="chip(numericQty === w)" @click="qty = String(w).replace('.', ',')">
                    {{ weightLabel(w) }} {{ unitLabel }}
                </button>
            </div>

            <!-- Dikerjakan oleh (komisi) -->
            <div v-if="showStaff">
                <p class="mb-2 text-lg font-bold text-ink">Dikerjakan oleh</p>
                <div class="flex flex-wrap gap-2">
                    <button v-for="s in store.boot.staff" :key="s.id" type="button" :class="chip(staffId === s.id)" @click="staffId = staffId === s.id ? null : s.id">
                        {{ s.name }}<span v-if="s.job_title" class="font-semibold text-ink-soft"> · {{ s.job_title }}</span>
                    </button>
                </div>
            </div>

            <MoneyInput v-if="canEditPrice" v-model="price" :label="pricingMode === 'per_weight' ? `Harga per ${unitLabel}` : 'Harga'" @update:model-value="priceTouched = true" />
            <p v-else class="text-lg text-ink-soft">Harga: <strong class="text-ink">{{ formatRupiah(price) }}</strong><template v-if="decimal"> per {{ unitLabel }}</template></p>

            <MoneyInput v-if="canDiscount" v-model="discount" label="Diskon untuk barang ini" optional />

            <div>
                <span class="mb-2 block text-lg font-bold text-ink">Catatan <span class="font-semibold text-ink-soft">(boleh dikosongkan)</span></span>
                <div v-if="quickNotes.length" class="mb-2 flex flex-wrap gap-2">
                    <button v-for="n in quickNotes" :key="n" type="button" :class="chip(note.split(', ').includes(n))" @click="toggleNote(n)">{{ n }}</button>
                </div>
                <input
                    v-model="note"
                    type="text"
                    maxlength="150"
                    placeholder="Ketik catatan lain"
                    aria-label="Catatan pesanan"
                    class="min-h-touch w-full rounded-2xl border-2 border-line bg-surface px-4 text-lg text-ink focus:border-focus focus:outline-none"
                />
            </div>

            <BigButton size="large" block class="justify-between!" @click="save">
                <span>{{ isAdd ? 'Tambahkan' : 'Simpan' }}</span>
                <span class="font-display tabular-nums">{{ formatRupiah(lineTotal) }}</span>
            </BigButton>

            <div v-if="!isAdd" class="grid gap-2 sm:grid-cols-2">
                <BigButton variant="ghost" class="text-danger-ink" @click="remove"><Trash2 :size="22" aria-hidden="true" /> Hapus dari pesanan</BigButton>
                <BigButton v-if="pricingMode === 'fixed'" variant="ghost" @click="soldOut"><Ban :size="22" aria-hidden="true" /> Tandai habis hari ini</BigButton>
            </div>
        </div>
    </BottomSheet>
</template>
