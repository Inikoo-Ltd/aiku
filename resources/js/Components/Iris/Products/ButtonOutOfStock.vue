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
        class="w-full cursor-not-allowed select-none rounded border border-gray-300 bg-gray-100 px-4 py-2 text-center text-gray-500">
        <div class="text-sm font-semibold">
            {{ label ?? ctrans("Out of stock") }}
        </div>
        <div v-if="expectedBackLabel" class="mt-0.5 text-xs font-normal text-gray-500">
            {{ expectedBackLabel }}
        </div>
    </div>
</template>
