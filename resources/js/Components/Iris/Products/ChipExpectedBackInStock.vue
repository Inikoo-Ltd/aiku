<script setup lang="ts">
import { computed } from "vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"

const props = defineProps<{
    product: {
        stock?: number | null
        is_coming_soon?: boolean
        expected_back_in_stock_at?: string | null
    }
}>()

const expectedBackDate = computed(() =>
    (props.product.stock ?? 0) > 0 || props.product.is_coming_soon || !props.product.expected_back_in_stock_at
        ? null
        : useFormatTime(props.product.expected_back_in_stock_at, { formatTime: "dd.MM.yyyy" })
)
</script>

<template>
    <div v-if="expectedBackDate"
        class="flex h-8 items-center whitespace-nowrap rounded-full bg-gray-100 px-3 text-xs text-gray-700 shadow-md">
        {{ ctrans("Expected Back on :date", { date: expectedBackDate }) }}
    </div>
</template>
