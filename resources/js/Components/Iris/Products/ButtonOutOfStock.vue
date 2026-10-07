<script setup lang="ts">
import { computed } from "vue"
import { ctrans } from "@/Composables/useTrans"
import { useExpectedBackInStockLabel } from "@/Composables/useOutOfStockLabel"

const props = defineProps<{
    product: {
        expected_back_in_stock_at?: string | null
    }
    label?: string | null
}>()

const expectedBackLabel = computed(() => useExpectedBackInStockLabel(props.product))
</script>

<template>
    <div
        role="status"
        aria-disabled="true"
        class="w-full cursor-not-allowed select-none rounded-md border border-gray-200 bg-gray-50 px-4 py-2.5 text-center">
        <div class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">
            {{ label ?? ctrans("Out of stock") }}
        </div>

        <div v-if="expectedBackLabel"
            class="mt-1.5 inline-block rounded-full bg-emerald-700 px-3 py-0.5 text-sm font-semibold text-white shadow-sm">
            {{ expectedBackLabel }}
        </div>
    </div>
</template>
