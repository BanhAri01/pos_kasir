<script setup>
/**
 * Keranjang: daftar pesanan, total, dan tombol BAYAR yang besar & tegas.
 * Dipakai di panel samping (tablet/laptop) dan di panel bawah (HP).
 * Kalau sedang melayani meja: bisa "Simpan ke Meja" (bayar nanti) atau "Pisah Bayar".
 */
import { computed } from 'vue';
import { Armchair, CheckSquare, Gift, Minus, Percent, Plus, Save, ShoppingBag, Square, Trash2, UserRound } from 'lucide-vue-next';
import BigButton from '@/Components/ui/BigButton.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import { formatRupiah } from '@/composables/useRupiah';
import { can, canRedeemMore, cancelRedeem, clearCart, customerPoints, hasModule, loyaltyRule, redeemReward, saveToTable, setQty, simpleMode, store, tableName, totals } from '../store';
import { lineMoney } from '../lib/calculator';
import { showToast } from '../lib/toast';

const emit = defineEmits(['edit', 'pay', 'customer', 'discount', 'tables']);

const isFnb = computed(() => store.boot.tenant.pos_layout === 'fnb');
const qtyText = (q) => String(q).replace('.', ',');
const step = (line) => (line.decimal ? 0.5 : 1);
const staffName = (id) => store.boot.staff.find((s) => s.id === id)?.name;
const splitting = computed(() => !!store.payOnlyKeys);

async function save() {
    await saveToTable();
    showToast('Pesanan disimpan ke meja.' + (hasModule('kitchen_display') ? ' Pesanan baru sudah dikirim ke dapur.' : ''));
}

function toggleSplit() {
    store.payOnlyKeys = splitting.value ? null : [];
}

function toggleLine(line) {
    const keys = store.payOnlyKeys;
    store.payOnlyKeys = keys.includes(line.key) ? keys.filter((k) => k !== line.key) : [...keys, line.key];
}
</script>

<template>
    <div class="flex h-full min-h-0 flex-col">
        <div class="flex items-center justify-between gap-2 pb-3">
            <h2 class="text-2xl font-extrabold text-ink">Pesanan</h2>
            <button
                v-if="store.cart.length && !store.tableId"
                type="button"
                class="pressable flex min-h-12 items-center gap-1 rounded-xl px-3 text-base font-bold text-danger-ink hover:bg-danger-soft"
                @click="clearCart"
            >
                <Trash2 :size="20" aria-hidden="true" /> Kosongkan
            </button>
        </div>

        <!-- Meja -->
        <button
            v-if="hasModule('tables') && store.boot.tables.length"
            type="button"
            class="pressable mb-3 flex min-h-12 items-center gap-2 rounded-xl border-2 px-3 text-left text-base font-bold"
            :class="store.tableId ? 'border-primary bg-primary-soft text-primary-ink' : 'border-dashed border-line text-ink-soft'"
            @click="emit('tables')"
        >
            <Armchair :size="22" aria-hidden="true" />
            <span class="flex-1">{{ store.tableId ? `Meja ${tableName(store.tableId)}` : 'Pilih meja (makan di sini)' }}</span>
            <span v-if="store.tableId" class="text-sm">Ganti</span>
        </button>

        <SegmentedControl
            v-if="isFnb && !store.tableId"
            v-model="store.orderType"
            label="Jenis pesanan"
            class="mb-3"
            :options="[
                { value: 'dine_in', label: 'Makan di sini' },
                { value: 'take_away', label: 'Bungkus' },
            ]"
        />

        <button
            type="button"
            class="pressable mb-3 flex min-h-12 items-center gap-2 rounded-xl border-2 border-dashed border-line px-3 text-left text-base font-bold"
            :class="store.customer ? 'border-solid border-primary bg-primary-soft text-primary-ink' : 'text-ink-soft'"
            @click="emit('customer')"
        >
            <UserRound :size="22" aria-hidden="true" />
            <span class="truncate">{{ store.customer ? store.customer.name : 'Tambah nama pelanggan (boleh dilewati)' }}</span>
            <span v-if="store.customer && loyaltyRule()" class="ml-auto shrink-0 text-sm">{{ customerPoints() }} poin</span>
        </button>

        <div v-if="store.customer && loyaltyRule() && store.cart.length && (canRedeemMore() || store.redeemPoints)" class="mb-3 flex items-center gap-2 rounded-xl bg-accent-soft p-2">
            <Gift :size="22" class="shrink-0 text-accent-ink" aria-hidden="true" />
            <span class="flex-1 text-base font-bold text-accent-ink">{{ store.redeemPoints ? `Tukar ${store.redeemPoints} poin` : `Bisa tukar ${loyaltyRule().points_for_reward} poin` }}</span>
            <button v-if="canRedeemMore()" type="button" class="pressable min-h-12 rounded-xl bg-accent px-3 text-base font-extrabold text-ink" @click="redeemReward() && showToast('Potongan tukar poin dipakai.')">
                {{ store.redeemPoints ? 'Tambah' : 'Tukar Poin' }}
            </button>
            <button v-if="store.redeemPoints" type="button" class="pressable min-h-12 rounded-xl px-3 text-base font-bold text-danger-ink" @click="cancelRedeem">Batal</button>
        </div>

        <p v-if="splitting" class="mb-2 rounded-xl bg-info-soft p-3 text-base font-bold text-info-ink">Centang pesanan yang dibayar sekarang. Sisanya tetap di meja.</p>

        <!-- Daftar barang -->
        <div class="-mx-1 min-h-0 flex-1 overflow-y-auto px-1">
            <div v-if="!store.cart.length" class="flex h-full min-h-40 flex-col items-center justify-center gap-2 text-center text-ink-soft">
                <ShoppingBag :size="44" aria-hidden="true" />
                <p class="text-lg">Belum ada pesanan.<br />Ketuk barang untuk menambahkan.</p>
            </div>
            <ul v-else class="flex flex-col gap-2">
                <li v-for="line in store.cart" :key="line.key" class="flex gap-2 rounded-2xl border-2 bg-surface p-3" :class="splitting && store.payOnlyKeys.includes(line.key) ? 'border-primary' : 'border-line'">
                    <button v-if="splitting" type="button" class="shrink-0 self-start text-primary-ink" :aria-label="`Bayar ${line.name} sekarang`" @click="toggleLine(line)">
                        <component :is="store.payOnlyKeys.includes(line.key) ? CheckSquare : Square" :size="30" aria-hidden="true" />
                    </button>
                    <div class="min-w-0 flex-1">
                        <button type="button" class="w-full text-left" @click="emit('edit', line)">
                            <span class="flex items-start justify-between gap-2">
                                <span class="text-lg leading-snug font-bold text-ink">{{ line.name }}</span>
                                <span class="font-display text-lg font-extrabold text-ink tabular-nums">{{ formatRupiah(lineMoney(line.unit_price, line.qty) - line.discount_amount) }}</span>
                            </span>
                            <span class="block text-base text-ink-soft">
                                {{ formatRupiah(line.unit_price) }}<template v-if="line.decimal || line.unit_id"> /{{ line.unit }}</template>
                                <template v-if="line.discount_amount"> · diskon {{ formatRupiah(line.discount_amount) }}</template>
                            </span>
                            <span v-if="line.modifiers.length" class="block text-base text-ink-soft">+ {{ line.modifiers.map((m) => m.name).join(', ') }}</span>
                            <span v-if="line.promo && !line.price_overridden" class="block text-base font-bold text-primary-ink">{{ line.promo.name }} −{{ formatRupiah(line.promo.discount) }}</span>
                            <span v-if="line.staff_id" class="block text-base font-bold text-info-ink">Oleh {{ staffName(line.staff_id) }}</span>
                            <span v-if="line.note" class="block text-base font-bold text-accent-ink">* {{ line.note }}</span>
                            <span v-if="line.sent_to_kitchen" class="block text-sm font-bold text-ink-soft">✓ Sudah dikirim ke dapur</span>
                        </button>
                        <div class="mt-2 flex items-center gap-2">
                            <button type="button" class="pressable flex size-12 items-center justify-center rounded-xl bg-surface-2 text-ink" :aria-label="`Kurangi ${line.name}`" @click="setQty(line, line.qty - step(line))">
                                <Minus :size="24" aria-hidden="true" />
                            </button>
                            <span class="min-w-14 text-center font-display text-xl font-extrabold text-ink tabular-nums">{{ qtyText(line.qty) }}<span v-if="line.decimal || line.unit_id" class="text-base"> {{ line.unit }}</span></span>
                            <button type="button" class="pressable flex size-12 items-center justify-center rounded-xl bg-surface-2 text-ink" :aria-label="`Tambah ${line.name}`" @click="setQty(line, line.qty + step(line))">
                                <Plus :size="24" aria-hidden="true" />
                            </button>
                            <button type="button" class="ml-auto min-h-12 rounded-xl px-3 text-base font-bold text-primary-ink hover:bg-primary-soft" @click="emit('edit', line)">Ubah</button>
                        </div>
                    </div>
                </li>
            </ul>
        </div>

        <!-- Total -->
        <div class="mt-3 border-t-2 border-line pt-3">
            <div v-if="totals.discountAmount || totals.serviceChargeAmount || totals.taxAmount" class="mb-2 flex flex-col gap-0.5 text-lg text-ink-soft">
                <p class="flex justify-between"><span>Subtotal</span><span class="tabular-nums">{{ formatRupiah(totals.subtotal) }}</span></p>
                <p v-if="totals.discountAmount" class="flex justify-between text-primary-ink"><span>Diskon</span><span class="tabular-nums">-{{ formatRupiah(totals.discountAmount) }}</span></p>
                <p v-if="totals.serviceChargeAmount" class="flex justify-between"><span>Biaya layanan</span><span class="tabular-nums">{{ formatRupiah(totals.serviceChargeAmount) }}</span></p>
                <p v-if="totals.taxAmount" class="flex justify-between">
                    <span>Pajak{{ store.boot.outlet.tax_inclusive ? ' (termasuk)' : '' }}</span><span class="tabular-nums">{{ formatRupiah(totals.taxAmount) }}</span>
                </p>
            </div>

            <div class="mb-2 grid gap-2" :class="store.tableId ? 'grid-cols-2' : ''">
                <button
                    v-if="store.cart.length && can('give_discount') && !simpleMode()"
                    type="button"
                    class="pressable flex min-h-12 items-center justify-center gap-2 rounded-xl border-2 border-line text-base font-bold text-ink"
                    :class="store.tableId ? 'col-span-2' : ''"
                    @click="emit('discount')"
                >
                    <Percent :size="20" aria-hidden="true" /> {{ store.discount.type ? 'Ubah diskon' : 'Beri diskon' }}
                </button>
                <template v-if="store.tableId && store.cart.length">
                    <BigButton variant="secondary" @click="save"><Save :size="20" aria-hidden="true" /> Simpan ke Meja</BigButton>
                    <BigButton variant="secondary" @click="toggleSplit">{{ splitting ? 'Bayar Semua' : 'Pisah Bayar' }}</BigButton>
                </template>
            </div>

            <BigButton size="large" block :disabled="!store.cart.length || (splitting && !store.payOnlyKeys.length)" class="justify-between!" @click="emit('pay')">
                <span>{{ splitting ? 'BAYAR YANG DIPILIH' : 'BAYAR' }}</span>
                <span class="font-display text-2xl tabular-nums">{{ formatRupiah(totals.total) }}</span>
            </BigButton>
        </div>
    </div>
</template>
