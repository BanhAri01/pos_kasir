<script setup>
/** Pilih pelanggan (cari nama / no HP) atau tambah pelanggan baru. */
import { ref, watch } from 'vue';
import { Search, UserPlus, UserRound, X } from 'lucide-vue-next';
import BigButton from '@/Components/ui/BigButton.vue';
import BigInput from '@/Components/ui/BigInput.vue';
import BottomSheet from '@/Components/ui/BottomSheet.vue';
import { createCustomer, hasModule, loyaltyRule, searchCustomers, setCustomer, store } from '../store';
import { formatRupiah } from '@/composables/useRupiah';
import { showToast } from '../lib/toast';

const open = defineModel('open', { type: Boolean, default: false });
const emit = defineEmits(['kasbon']);
const showBalance = hasModule('kasbon') || hasModule('credit_sales');

const query = ref('');
const results = ref([]);
const adding = ref(false);
const form = ref({ name: '', phone: '' });
const saving = ref(false);
let timer = null;

watch(open, (v) => {
    if (v) {
        query.value = '';
        results.value = [];
        adding.value = false;
        form.value = { name: '', phone: '' };
        search('');
    }
});

watch(query, (q) => {
    clearTimeout(timer);
    timer = setTimeout(() => search(q), 300);
});

async function search(q) {
    try {
        results.value = await searchCustomers(q);
    } catch {
        results.value = [];
    }
}

function choose(customer) {
    setCustomer(customer);
    open.value = false;
}

function payKasbon(customer) {
    open.value = false;
    emit('kasbon', customer);
}

async function save() {
    if (!form.value.name.trim()) {
        showToast('Nama pelanggan belum diisi.', 'error');
        return;
    }
    saving.value = true;
    try {
        choose(await createCustomer(form.value));
        showToast('Pelanggan baru disimpan.');
    } catch (e) {
        showToast(e.message, 'error');
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <BottomSheet v-model:open="open" title="Pelanggan">
        <div v-if="!adding" class="flex flex-col gap-3">
            <BigButton v-if="store.customer" variant="ghost" class="text-danger-ink" @click="choose(null)">
                <X :size="22" aria-hidden="true" /> Hapus pelanggan dari pesanan ini
            </BigButton>
            <label class="relative block">
                <span class="sr-only">Cari pelanggan</span>
                <Search :size="24" class="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-ink-soft" aria-hidden="true" />
                <input
                    v-model="query"
                    type="search"
                    placeholder="Cari nama atau no HP"
                    class="min-h-touch w-full rounded-2xl border-2 border-line bg-surface pr-4 pl-13 text-lg text-ink focus:border-focus focus:outline-none"
                />
            </label>
            <ul class="flex flex-col gap-2">
                <li v-for="c in results" :key="c.uuid" class="flex gap-2">
                    <button type="button" class="pressable flex min-h-touch flex-1 items-center gap-3 rounded-2xl border-2 border-line px-4 py-2 text-left hover:border-primary" @click="choose(c)">
                        <UserRound :size="24" class="text-ink-soft" aria-hidden="true" />
                        <span class="min-w-0">
                            <span class="block text-lg font-bold text-ink">{{ c.name }}</span>
                            <span v-if="c.phone_display" class="block text-base text-ink-soft">{{ c.phone_display }}</span>
                            <span v-if="showBalance && c.balance > 0" class="block text-base font-bold text-danger-ink">Utang {{ formatRupiah(c.balance) }}</span>
                            <span v-if="loyaltyRule() && c.points" class="block text-base font-bold text-accent-ink">{{ c.points }} poin</span>
                        </span>
                    </button>
                    <button v-if="showBalance && c.balance > 0" type="button" class="pressable min-h-touch shrink-0 rounded-2xl bg-accent-soft px-3 text-base font-bold text-accent-ink" @click="payKasbon(c)">Bayar<br />Utang</button>
                </li>
            </ul>
            <BigButton variant="soft" block @click="adding = true; form.name = query">
                <UserPlus :size="24" aria-hidden="true" /> Tambah Pelanggan Baru
            </BigButton>
        </div>

        <div v-else class="flex flex-col gap-4">
            <BigInput v-model="form.name" label="Nama pelanggan" placeholder="Contoh: Pak Joko" />
            <BigInput v-model="form.phone" label="No HP (WhatsApp)" type="tel" inputmode="tel" optional placeholder="0812 3456 7890" hint="Untuk kirim struk & pengingat lewat WhatsApp." />
            <BigButton block size="large" :loading="saving" @click="save">Simpan Pelanggan</BigButton>
            <BigButton variant="ghost" block @click="adding = false">Kembali ke pencarian</BigButton>
        </div>
    </BottomSheet>
</template>
