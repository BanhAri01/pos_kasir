<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Minus, Plus, QrCode, ShoppingBag, Store, Trash2, UtensilsCrossed } from 'lucide-vue-next';
import BigButton from '@/Components/ui/BigButton.vue';
import BigInput from '@/Components/ui/BigInput.vue';
import BottomSheet from '@/Components/ui/BottomSheet.vue';
import { formatRupiah } from '@/composables/useRupiah';

const props = defineProps({
    token: { type: String, required: true },
    business: { type: Object, required: true },
    table: { type: String, required: true },
    open: { type: Boolean, default: false },
    menu: { type: Object, required: true },
    charges: { type: Object, required: true },
    online: { type: Boolean, default: false },
    qrisFee: { type: Object, default: null },
    feeVatBp: { type: Number, default: 0 },
});

const STORAGE_KEY = `hermes.order.${props.token}`;
const category = ref(null);
const cart = ref(loadCart());
const picking = ref(null);
const choice = reactive({ qty: 1, modifiers: {}, note: '' });
const checkoutOpen = ref(false);
const form = useForm({ customer_name: loadName(), note: '', pay_method: props.online ? 'online' : 'cashier', items: [] });

const productsById = computed(() => Object.fromEntries(props.menu.products.map((p) => [p.id, p])));
const visibleProducts = computed(() => (category.value ? props.menu.products.filter((p) => p.category_id === category.value) : props.menu.products));
const count = computed(() => cart.value.reduce((s, l) => s + l.qty, 0));
const subtotal = computed(() => cart.value.reduce((s, l) => s + l.unit_price * l.qty, 0));

const totals = computed(() => {
    const base = subtotal.value;
    const service = Math.round((base * props.charges.service_charge_bp) / 10000);
    const taxable = base + service;
    const tax = props.charges.tax_inclusive
        ? props.charges.tax_bp > 0 ? Math.round((taxable * props.charges.tax_bp) / (10000 + props.charges.tax_bp)) : 0
        : Math.round((taxable * props.charges.tax_bp) / 10000);
    const total = props.charges.tax_inclusive ? taxable : taxable + tax;
    return { base, service, tax, total };
});

const fee = computed(() => {
    if (form.pay_method !== 'online' || !props.qrisFee) return 0;
    const vat = 10000 + props.feeVatBp;
    const numerator = totals.value.total * 100000000 + props.qrisFee.flat * vat * 10000;
    const denominator = 100000000 - props.qrisFee.percent_bp * vat;
    return Math.max(0, Math.ceil(numerator / denominator) - totals.value.total);
});

function loadCart() {
    try {
        const saved = JSON.parse(sessionStorage.getItem(STORAGE_KEY) ?? '[]');
        return Array.isArray(saved) ? saved : [];
    } catch {
        return [];
    }
}

function loadName() {
    try {
        return localStorage.getItem('hermes.order.name') ?? '';
    } catch {
        return '';
    }
}

function saveName(name) {
    try {
        localStorage.setItem('hermes.order.name', name);
        return true;
    } catch {
        return false;
    }
}

function persist() {
    try {
        sessionStorage.setItem(STORAGE_KEY, JSON.stringify(cart.value));
        return true;
    } catch {
        return false;
    }
}

function lineKey(productId, modifierIds, note) {
    return `${productId}|${[...modifierIds].sort((a, b) => a - b).join(',')}|${note}`;
}

function addLine(product, qty, modifierIds, note) {
    const options = product.modifier_groups.flatMap((g) => g.options);
    const chosen = options.filter((o) => modifierIds.includes(o.id));
    const unitPrice = product.price + chosen.reduce((s, o) => s + o.price_delta, 0);
    const key = lineKey(product.id, modifierIds, note);
    const existing = cart.value.find((l) => l.key === key);
    if (existing) existing.qty = Math.min(50, existing.qty + qty);
    else cart.value.push({ key, product_id: product.id, name: product.name, unit_price: unitPrice, qty, modifier_ids: modifierIds, modifiers: chosen.map((o) => o.name), note });
    persist();
}

function tapProduct(product) {
    if (!product.modifier_groups.length) {
        addLine(product, 1, [], '');
        return;
    }
    picking.value = product;
    choice.qty = 1;
    choice.note = '';
    choice.modifiers = Object.fromEntries(product.modifier_groups.map((g) => [g.id, g.selection === 'single' && g.is_required && g.options[0] ? [g.options[0].id] : []]));
}

function toggleOption(group, option) {
    const current = choice.modifiers[group.id] ?? [];
    if (group.selection === 'single') choice.modifiers[group.id] = current.includes(option.id) && !group.is_required ? [] : [option.id];
    else choice.modifiers[group.id] = current.includes(option.id) ? current.filter((id) => id !== option.id) : [...current, option.id];
}

const pickingPrice = computed(() => {
    if (!picking.value) return 0;
    const ids = Object.values(choice.modifiers).flat();
    const extra = picking.value.modifier_groups.flatMap((g) => g.options).filter((o) => ids.includes(o.id)).reduce((s, o) => s + o.price_delta, 0);
    return (picking.value.price + extra) * choice.qty;
});
const missingGroup = computed(() => picking.value?.modifier_groups.find((g) => g.is_required && !(choice.modifiers[g.id] ?? []).length) ?? null);

function confirmPick() {
    if (missingGroup.value) return;
    addLine(picking.value, choice.qty, Object.values(choice.modifiers).flat(), choice.note.trim());
    picking.value = null;
}

function changeQty(line, delta) {
    line.qty = Math.min(50, line.qty + delta);
    if (line.qty <= 0) cart.value = cart.value.filter((l) => l.key !== line.key);
    persist();
}

const qtyInCart = (productId) => cart.value.filter((l) => l.product_id === productId).reduce((s, l) => s + l.qty, 0);

function submit() {
    saveName(form.customer_name.trim());
    form.items = cart.value.map((l) => ({ product_id: l.product_id, qty: l.qty, modifier_ids: l.modifier_ids, note: l.note || null }));
    form.post(route('self-order.store', props.token), {
        preserveScroll: true,
        onSuccess: () => {
            cart.value = [];
            persist();
        },
    });
}
</script>

<template>
    <Head :title="`Menu ${business.name}`" />
    <div class="min-h-dvh bg-page pb-36">
        <header class="bg-brand px-4 pt-safe pb-5 text-white">
            <div class="mx-auto flex max-w-2xl items-center gap-3 pt-5">
                <img v-if="business.logo_url" :src="business.logo_url" alt="" class="size-14 rounded-2xl bg-white object-cover" />
                <span v-else class="flex size-14 items-center justify-center rounded-2xl bg-accent text-ink"><Store :size="28" aria-hidden="true" /></span>
                <div class="min-w-0 flex-1">
                    <h1 class="truncate font-display text-2xl font-extrabold">{{ business.name }}</h1>
                    <p class="truncate text-base text-white/80">{{ business.outlet }}</p>
                </div>
                <span class="shrink-0 rounded-2xl bg-accent px-3 py-2 text-center text-ink">
                    <span class="block text-sm font-bold">Meja</span>
                    <span class="block font-display text-xl leading-none font-extrabold">{{ table.replace(/^Meja\s*/i, '') }}</span>
                </span>
            </div>
        </header>

        <main class="mx-auto max-w-2xl px-4">
            <section v-if="!open" class="card mt-6 flex flex-col items-center gap-3 p-8 text-center">
                <UtensilsCrossed :size="44" class="text-ink-soft" aria-hidden="true" />
                <h2 class="text-2xl font-extrabold text-ink">Pesan lewat QR sedang tidak aktif</h2>
                <p class="text-lg text-ink-soft">Silakan panggil pelayan atau pesan langsung di kasir.</p>
            </section>

            <template v-else>
                <nav v-if="menu.categories.length > 1" class="sticky top-0 z-10 -mx-4 flex gap-2 overflow-x-auto bg-page px-4 py-3" aria-label="Kategori menu">
                    <button type="button" class="pressable shrink-0 rounded-full px-4 py-2 text-lg font-bold" :class="category === null ? 'bg-primary text-on-primary' : 'bg-surface text-ink'" @click="category = null">Semua</button>
                    <button
                        v-for="c in menu.categories"
                        :key="c.id"
                        type="button"
                        class="pressable shrink-0 rounded-full px-4 py-2 text-lg font-bold"
                        :class="category === c.id ? 'bg-primary text-on-primary' : 'bg-surface text-ink'"
                        @click="category = c.id"
                    >{{ c.name }}</button>
                </nav>

                <p v-if="!visibleProducts.length" class="card mt-6 p-6 text-center text-lg text-ink-soft">Menu belum tersedia.</p>
                <ul class="mt-3 grid gap-3">
                    <li v-for="p in visibleProducts" :key="p.id" class="card flex gap-3 p-3">
                        <img v-if="p.image_url" :src="p.image_url" :alt="p.name" loading="lazy" class="size-24 shrink-0 rounded-2xl object-cover" />
                        <div class="flex min-w-0 flex-1 flex-col">
                            <p class="text-lg leading-tight font-extrabold text-ink">{{ p.name }}</p>
                            <p v-if="p.description" class="line-clamp-2 text-base text-ink-soft">{{ p.description }}</p>
                            <p v-if="p.promo" class="text-sm font-bold text-accent-ink">{{ p.promo }}</p>
                            <div class="mt-auto flex items-center justify-between gap-2 pt-2">
                                <span class="font-display text-xl font-extrabold text-ink">{{ formatRupiah(p.price) }}</span>
                                <button type="button" class="pressable flex min-h-12 items-center gap-1 rounded-2xl bg-primary px-4 text-lg font-bold text-on-primary" @click="tapProduct(p)">
                                    <Plus :size="20" aria-hidden="true" /> {{ qtyInCart(p.id) ? `${qtyInCart(p.id)} dipilih` : 'Tambah' }}
                                </button>
                            </div>
                        </div>
                    </li>
                </ul>
            </template>
        </main>

        <div v-if="open && count" class="fixed inset-x-0 bottom-0 z-20 bg-gradient-to-t from-page via-page px-4 pt-6 pb-safe">
            <button type="button" class="pressable mx-auto mb-4 flex min-h-touch-lg w-full max-w-2xl items-center gap-3 rounded-3xl bg-primary px-5 text-on-primary shadow-lg" @click="checkoutOpen = true">
                <ShoppingBag :size="26" aria-hidden="true" />
                <span class="flex-1 text-left text-lg font-bold">{{ count }} menu dipilih</span>
                <span class="font-display text-xl font-extrabold">{{ formatRupiah(totals.total) }}</span>
            </button>
        </div>

        <BottomSheet :open="!!picking" :title="picking?.name ?? ''" @update:open="(v) => !v && (picking = null)">
            <div v-if="picking" class="flex flex-col gap-5">
                <div v-for="g in picking.modifier_groups" :key="g.id">
                    <p class="mb-2 text-lg font-bold text-ink">{{ g.name }} <span class="text-base font-normal text-ink-soft">{{ g.is_required ? '(wajib)' : '(boleh dikosongkan)' }}</span></p>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="o in g.options"
                            :key="o.id"
                            type="button"
                            class="pressable min-h-12 rounded-2xl border-2 px-4 text-lg font-bold"
                            :class="(choice.modifiers[g.id] ?? []).includes(o.id) ? 'border-primary bg-primary-soft text-primary-ink' : 'border-line text-ink'"
                            :aria-pressed="(choice.modifiers[g.id] ?? []).includes(o.id)"
                            @click="toggleOption(g, o)"
                        >{{ o.name }}<template v-if="o.price_delta"> +{{ formatRupiah(o.price_delta) }}</template></button>
                    </div>
                </div>
                <BigInput v-model="choice.note" label="Catatan" optional placeholder="Contoh: tidak pedas" :maxlength="150" />
                <div class="flex items-center justify-between gap-3">
                    <span class="text-lg font-bold text-ink">Jumlah</span>
                    <div class="flex items-center gap-3">
                        <button type="button" class="pressable flex size-12 items-center justify-center rounded-2xl bg-surface-2 text-ink" aria-label="Kurangi" @click="choice.qty = Math.max(1, choice.qty - 1)"><Minus :size="22" /></button>
                        <span class="w-8 text-center font-display text-2xl font-extrabold text-ink">{{ choice.qty }}</span>
                        <button type="button" class="pressable flex size-12 items-center justify-center rounded-2xl bg-surface-2 text-ink" aria-label="Tambah" @click="choice.qty = Math.min(50, choice.qty + 1)"><Plus :size="22" /></button>
                    </div>
                </div>
                <p v-if="missingGroup" class="text-base font-bold text-danger-ink">Pilih {{ missingGroup.name }} dulu.</p>
                <BigButton block size="large" :disabled="!!missingGroup" @click="confirmPick">Tambah {{ formatRupiah(pickingPrice) }}</BigButton>
            </div>
        </BottomSheet>

        <BottomSheet v-model:open="checkoutOpen" title="Pesanan Anda">
            <ul class="mb-4 divide-y divide-line">
                <li v-for="l in cart" :key="l.key" class="flex items-start gap-3 py-3">
                    <div class="min-w-0 flex-1">
                        <p class="text-lg font-bold text-ink">{{ l.name }}</p>
                        <p v-if="l.modifiers.length" class="text-base text-ink-soft">{{ l.modifiers.join(', ') }}</p>
                        <p v-if="l.note" class="text-base text-ink-soft">Catatan: {{ l.note }}</p>
                        <p class="text-base font-bold text-ink">{{ formatRupiah(l.unit_price * l.qty) }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" class="pressable flex size-11 items-center justify-center rounded-2xl bg-surface-2 text-ink" :aria-label="l.qty === 1 ? `Hapus ${l.name}` : `Kurangi ${l.name}`" @click="changeQty(l, -1)">
                            <component :is="l.qty === 1 ? Trash2 : Minus" :size="20" />
                        </button>
                        <span class="w-7 text-center text-xl font-extrabold text-ink">{{ l.qty }}</span>
                        <button type="button" class="pressable flex size-11 items-center justify-center rounded-2xl bg-surface-2 text-ink" :aria-label="`Tambah ${l.name}`" @click="changeQty(l, 1)"><Plus :size="20" /></button>
                    </div>
                </li>
            </ul>

            <div class="flex flex-col gap-4">
                <BigInput v-model="form.customer_name" label="Nama Anda" placeholder="Contoh: Dewi" :maxlength="60" :error="form.errors.customer_name" />
                <BigInput v-model="form.note" label="Catatan untuk dapur" optional placeholder="Contoh: es dipisah" :maxlength="200" />

                <div v-if="online">
                    <p class="mb-2 text-lg font-bold text-ink">Cara bayar</p>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <button type="button" class="pressable flex items-center gap-3 rounded-2xl border-2 p-3 text-left" :class="form.pay_method === 'online' ? 'border-primary bg-primary-soft' : 'border-line'" @click="form.pay_method = 'online'">
                            <QrCode :size="26" class="shrink-0 text-ink" aria-hidden="true" />
                            <span><span class="block text-lg font-bold text-ink">Bayar QRIS sekarang</span><span class="block text-base text-ink-soft">Semua e-wallet & m-banking</span></span>
                        </button>
                        <button type="button" class="pressable flex items-center gap-3 rounded-2xl border-2 p-3 text-left" :class="form.pay_method === 'cashier' ? 'border-primary bg-primary-soft' : 'border-line'" @click="form.pay_method = 'cashier'">
                            <Store :size="26" class="shrink-0 text-ink" aria-hidden="true" />
                            <span><span class="block text-lg font-bold text-ink">Bayar di kasir</span><span class="block text-base text-ink-soft">Setelah selesai makan</span></span>
                        </button>
                    </div>
                </div>

                <dl class="rounded-2xl bg-surface-2 p-4 text-lg">
                    <div class="flex justify-between"><dt class="text-ink-soft">Subtotal</dt><dd class="font-bold text-ink">{{ formatRupiah(totals.base) }}</dd></div>
                    <div v-if="totals.service" class="flex justify-between"><dt class="text-ink-soft">Biaya layanan</dt><dd class="font-bold text-ink">{{ formatRupiah(totals.service) }}</dd></div>
                    <div v-if="totals.tax" class="flex justify-between"><dt class="text-ink-soft">Pajak{{ charges.tax_inclusive ? ' (sudah termasuk)' : '' }}</dt><dd class="font-bold text-ink">{{ formatRupiah(totals.tax) }}</dd></div>
                    <div v-if="fee" class="flex justify-between"><dt class="text-ink-soft">Biaya bayar QRIS</dt><dd class="font-bold text-ink">{{ formatRupiah(fee) }}</dd></div>
                    <div class="mt-2 flex justify-between border-t border-line pt-2"><dt class="font-extrabold text-ink">Total</dt><dd class="font-display text-2xl font-extrabold text-ink">{{ formatRupiah(totals.total + fee) }}</dd></div>
                </dl>

                <p v-if="form.errors.items" class="text-lg font-bold text-danger-ink">{{ form.errors.items }}</p>
                <p v-if="form.errors.pay_method" class="text-lg font-bold text-danger-ink">{{ form.errors.pay_method }}</p>
                <BigButton block size="large" :loading="form.processing" :disabled="!cart.length" @click="submit">
                    {{ form.pay_method === 'online' ? `Pesan & Bayar ${formatRupiah(totals.total + fee)}` : 'Kirim Pesanan' }}
                </BigButton>
                <p class="text-center text-base text-ink-soft">Pesanan akan dicek kasir lalu dibuatkan.</p>
            </div>
        </BottomSheet>
    </div>
</template>
