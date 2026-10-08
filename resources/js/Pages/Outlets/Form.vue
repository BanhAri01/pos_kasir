<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import ToggleSwitch from '@/Components/ui/ToggleSwitch.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import BigButton from '@/Components/ui/BigButton.vue';
import BigInput from '@/Components/ui/BigInput.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { useAuth } from '@/composables/useAuth';

defineOptions({ layout: AppLayout });

const props = defineProps({
    outlet: { type: Object, default: null },
});

const form = useForm({
    name: props.outlet?.name ?? '',
    type: props.outlet?.type ?? 'store',
    address: props.outlet?.address ?? '',
    phone: props.outlet?.phone ?? '',
    tax_rate: props.outlet?.tax_rate ?? '0',
    tax_inclusive: props.outlet?.tax_inclusive ?? false,
    service_charge: props.outlet?.service_charge ?? '0',
    receipt_header: props.outlet?.receipt_header ?? '',
    receipt_footer: props.outlet?.receipt_footer ?? '',
    receipt_paper: props.outlet?.receipt_paper ?? '58',
    document_paper: props.outlet?.document_paper ?? 'a4',
});

const { hasModule } = useAuth();

const showTax = ref(props.outlet ? props.outlet.tax_rate !== '0' || props.outlet.service_charge !== '0' || !!props.outlet.receipt_footer : false);

function submit() {
    if (props.outlet) {
        form.put(route('outlets.update', props.outlet.id));
    } else {
        form.post(route('outlets.store'));
    }
}
</script>

<template>
    <Head :title="outlet ? 'Ubah Outlet' : 'Tambah Outlet'" />

    <div class="mx-auto max-w-2xl">
        <PageHeader :title="outlet ? 'Ubah Outlet' : 'Tambah Outlet'" :back-href="route('outlets.index')" help="outlet-form" />

        <form class="card flex flex-col gap-5 p-5 sm:p-8" @submit.prevent="submit">
            <div v-if="hasModule('multi_warehouse')">
                <span class="mb-2 block text-lg font-bold text-ink">Jenis tempat</span>
                <SegmentedControl v-model="form.type" label="Jenis tempat" :options="[{ value: 'store', label: 'Toko' }, { value: 'warehouse', label: 'Gudang' }]" />
                <p class="mt-2 text-base text-ink-soft">Gudang dipakai untuk menyimpan stok. Stoknya terpisah dan bisa dikirim ke toko atau gudang lain.</p>
            </div>
            <BigInput v-model="form.name" :label="form.type === 'warehouse' ? 'Nama gudang' : 'Nama outlet'" :placeholder="form.type === 'warehouse' ? 'Contoh: Gudang Utama' : 'Contoh: Cabang Pasar Baru'" :error="form.errors.name" />
            <BigInput v-model="form.address" label="Alamat" optional placeholder="Contoh: Jl. Merdeka No. 10" :error="form.errors.address" />
            <BigInput
                v-model="form.phone"
                label="No HP outlet"
                type="tel"
                inputmode="tel"
                optional
                placeholder="0812 3456 7890"
                hint="Akan tampil di struk."
                :error="form.errors.phone"
            />

            <!-- Pajak & struk (disembunyikan supaya form tetap pendek) -->
            <div class="rounded-2xl border-2 border-line p-4">
                <button type="button" class="flex min-h-12 w-full items-center justify-between text-left text-xl font-extrabold text-ink" :aria-expanded="showTax" @click="showTax = !showTax">
                    Pajak, biaya layanan & struk
                    <ChevronDown :size="26" class="transition-transform" :class="showTax ? 'rotate-180' : ''" aria-hidden="true" />
                </button>
                <div v-if="showTax" class="mt-4 flex flex-col gap-5">
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <BigInput v-model="form.tax_rate" label="Pajak (%)" inputmode="decimal" hint="Isi 0 kalau tidak memungut pajak." :error="form.errors.tax_rate" />
                        <BigInput v-model="form.service_charge" label="Biaya layanan (%)" inputmode="decimal" hint="Biasanya untuk rumah makan. Isi 0 kalau tidak ada." :error="form.errors.service_charge" />
                    </div>
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                        <span class="min-w-48 flex-1 text-lg text-ink">Harga jual sudah termasuk pajak</span>
                        <ToggleSwitch v-model="form.tax_inclusive" label="Harga sudah termasuk pajak" class="ml-auto" />
                    </div>
                    <BigInput v-model="form.receipt_header" label="Tulisan di atas struk" optional placeholder="Contoh: Buka setiap hari 07.00 - 21.00" />
                    <BigInput v-model="form.receipt_footer" label="Tulisan di bawah struk" optional placeholder="Contoh: Terima kasih, datang lagi ya!" />
                    <div>
                        <span class="mb-2 block text-lg font-bold text-ink">Lebar kertas struk</span>
                        <SegmentedControl v-model="form.receipt_paper" label="Lebar kertas struk" :options="[{ value: '58', label: '58 mm (kecil)' }, { value: '80', label: '80 mm (besar)' }]" />
                    </div>
                    <div>
                        <span class="mb-2 block text-lg font-bold text-ink">Kertas faktur & surat jalan</span>
                        <SegmentedControl v-model="form.document_paper" label="Kertas faktur dan surat jalan" :options="[{ value: 'a4', label: 'A4' }, { value: 'kontinyu', label: 'Kontinyu 9,5 x 11 inci' }]" />
                        <span class="mt-2 block text-base text-ink-soft">Pilih kontinyu untuk printer dot matrix (contoh Epson LX-310) dengan kertas berlubang 3 rangkap.</span>
                    </div>
                </div>
            </div>

            <BigButton type="submit" size="large" block :loading="form.processing">Simpan</BigButton>
        </form>
    </div>
</template>
