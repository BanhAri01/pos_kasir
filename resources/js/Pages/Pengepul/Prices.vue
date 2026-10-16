<script setup>
/**
 * Harga Hari Ini: isi harga beli (dari peternak) & harga jual per jenis telur.
 * Sekali tekan simpan, harga jual langsung dipakai di kasir dan faktur.
 */
import { computed, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Check, Settings, TriangleAlert } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { formatRupiah } from '@/composables/useRupiah';

defineOptions({ layout: AppLayout });

const tanggal = (d) => (d ? new Date(`${d}T00:00:00`).toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'short', year: 'numeric' }) : '');

const props = defineProps({
    date: { type: String, required: true },
    outlet: { type: Object, required: true },
    rows: { type: Array, required: true },
});

const buildPrices = (rows) =>
    rows.map((r) => ({
        product_id: r.product_id,
        buy_price: r.buy_price ?? null,
        sell_price: r.sell_price ?? 0,
    }));

const form = useForm({
    prices: buildPrices(props.rows),
});

watch(
    () => props.rows,
    (rows) => {
        form.prices = buildPrices(rows);
    },
);

const hasUnfilled = computed(() => props.rows.some((r) => !r.filled_today));

function save() {
    form.post(route('egg.prices.store'), { preserveScroll: true });
}
</script>

<template>
    <Head title="Harga Telur Hari Ini" />
    <div class="mx-auto max-w-3xl">
        <PageHeader title="Harga Telur Hari Ini" :subtitle="`${outlet.name} · ${tanggal(date)}`">
            <template #action>
                <BigButton :href="route('egg.types')" variant="secondary">
                    <Settings :size="22" aria-hidden="true" /> Jenis Telur
                </BigButton>
            </template>
        </PageHeader>

        <EmptyState
            v-if="!rows.length"
            title="Belum ada jenis telur"
            message="Pilih dulu barang mana yang termasuk telur campur dan mana yang hasil sortir."
        >
            <BigButton :href="route('egg.types')">Atur Jenis Telur</BigButton>
        </EmptyState>

        <form v-else class="flex flex-col gap-5" @submit.prevent="save">
            <div
                v-if="hasUnfilled"
                class="flex items-start gap-3 rounded-2xl bg-warn-soft p-4 text-lg font-bold text-warn-ink"
                role="status"
            >
                <TriangleAlert :size="26" class="mt-0.5 shrink-0" aria-hidden="true" />
                <span>Harga hari ini belum diisi. Angka di bawah adalah harga terakhir, silakan cek lalu tekan Simpan.</span>
            </div>
            <div
                v-else
                class="flex items-center gap-3 rounded-2xl bg-surface-2 px-4 py-3 text-lg font-bold text-ink"
            >
                <Check :size="24" class="shrink-0 text-primary-ink" aria-hidden="true" />
                <span>Harga hari ini sudah tersimpan. Kasir otomatis memakai harga di bawah.</span>
            </div>

            <p v-if="form.errors.prices" role="alert" class="rounded-2xl bg-danger-soft p-4 text-lg font-bold text-danger-ink">
                {{ form.errors.prices }}
            </p>

            <section
                v-for="(row, i) in rows"
                :key="row.product_id"
                class="card flex flex-col gap-4 p-5"
            >
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <span
                            class="mb-1 inline-block rounded-xl px-3 py-1 text-base font-bold"
                            :class="row.role === 'mixed' ? 'bg-accent-soft text-accent-ink' : 'bg-primary-soft text-primary-ink'"
                        >
                            {{ row.role === 'mixed' ? 'Telur campur (beli dari peternak)' : 'Hasil sortir' }}
                        </span>
                        <h2 class="text-2xl font-extrabold text-ink">{{ row.name }}</h2>
                    </div>
                    <span
                        v-if="!row.filled_today"
                        class="rounded-xl bg-warn-soft px-3 py-1 text-base font-bold text-warn-ink"
                    >
                        Harga hari ini belum diisi
                    </span>
                </div>

                <p v-if="row.previous" class="-mt-1 text-base text-ink-soft">
                    Harga terakhir ({{ tanggal(row.previous.date) }}):
                    <template v-if="row.previous.buy_price !== null">
                        beli <strong class="text-ink tabular-nums">{{ formatRupiah(row.previous.buy_price) }}</strong>/{{ row.unit }} ·
                    </template>
                    jual <strong class="text-ink tabular-nums">{{ formatRupiah(row.previous.sell_price) }}</strong>/{{ row.unit }}
                </p>

                <div v-if="form.prices[i]" class="grid gap-4 sm:grid-cols-2">
                    <template v-if="row.role === 'mixed'">
                        <MoneyInput
                            v-model="form.prices[i].buy_price"
                            :label="`Harga beli per ${row.unit} (peternak)`"
                            :error="form.errors[`prices.${i}.buy_price`]"
                        />
                        <MoneyInput
                            v-model="form.prices[i].sell_price"
                            :label="`Harga jual per ${row.unit}`"
                            :error="form.errors[`prices.${i}.sell_price`] ?? form.errors[`prices.${i}.product_id`]"
                        />
                    </template>
                    <template v-else>
                        <MoneyInput
                            v-model="form.prices[i].sell_price"
                            :label="`Harga jual per ${row.unit}`"
                            :error="form.errors[`prices.${i}.sell_price`] ?? form.errors[`prices.${i}.product_id`]"
                        />
                        <MoneyInput
                            v-model="form.prices[i].buy_price"
                            :label="`Harga beli per ${row.unit}`"
                            optional
                            hint="Isi hanya bila beli langsung dari peternak"
                            :error="form.errors[`prices.${i}.buy_price`]"
                        />
                    </template>
                </div>
            </section>

            <BigButton type="submit" size="large" block :loading="form.processing">
                Simpan harga hari ini
            </BigButton>
        </form>

        <p class="mt-6 text-center">
            <Link :href="route('egg.types')" class="text-lg font-bold text-primary-ink">
                Atur jenis telur campur &amp; hasil sortir
            </Link>
        </p>
    </div>
</template>
