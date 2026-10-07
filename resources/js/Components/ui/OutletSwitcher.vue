<script setup>
/**
 * Tombol ganti outlet. Hanya muncul kalau pengguna punya akses ke lebih dari satu outlet.
 */
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { Check, ChevronDown, Store } from 'lucide-vue-next';
import BottomSheet from './BottomSheet.vue';

const page = usePage();
const open = ref(false);
const outlet = computed(() => page.props.outlet);

function choose(id) {
    open.value = false;
    if (id !== outlet.value?.current?.id) {
        router.post(route('outlets.switch'), { outlet_id: id }, { preserveScroll: true });
    }
}
</script>

<template>
    <template v-if="outlet?.list?.length > 1">
        <button
            type="button"
            class="pressable flex min-h-12 max-w-full items-center gap-2 rounded-full bg-surface-2 px-4 text-base font-bold text-ink"
            @click="open = true"
        >
            <Store :size="20" class="shrink-0" aria-hidden="true" />
            <span class="truncate">{{ outlet.current?.name }}</span>
            <ChevronDown :size="20" class="shrink-0" aria-hidden="true" />
        </button>

        <BottomSheet v-model:open="open" title="Pilih outlet">
            <ul class="flex flex-col gap-2">
                <li v-for="item in outlet.list" :key="item.id">
                    <button
                        type="button"
                        class="pressable flex min-h-touch w-full items-center gap-3 rounded-2xl border-2 px-4 text-left text-lg font-bold"
                        :class="item.id === outlet.current?.id ? 'border-primary bg-primary-soft text-ink' : 'border-line text-ink'"
                        @click="choose(item.id)"
                    >
                        <Store :size="24" aria-hidden="true" />
                        <span class="flex-1">{{ item.name }}<span v-if="item.type === 'warehouse'" class="ml-2 text-base font-bold text-ink-soft">· Gudang</span></span>
                        <Check v-if="item.id === outlet.current?.id" :size="24" class="text-primary-ink" aria-label="Sedang dipakai" />
                    </button>
                </li>
            </ul>
        </BottomSheet>
    </template>
</template>
