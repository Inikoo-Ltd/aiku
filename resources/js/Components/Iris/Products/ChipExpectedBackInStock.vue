<script setup lang="ts">
import { computed } from "vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"

const props = defineProps<{
    product: {
        is_back_in_stock?: boolean
        expected_back_in_stock_at?: string | null
    }
}>()

const emits = defineEmits<{
    (e: "toggle"): void
}>()

const expectedBackDate = computed(() =>
    props.product.expected_back_in_stock_at
        ? useFormatTime(props.product.expected_back_in_stock_at, { formatTime: "dd.MM.yyyy" })
        : null
)
</script>

<template>
    <button type="button"
        v-tooltip="expectedBackDate ? ctrans('Expected Back on :date', { date: expectedBackDate }) : null"
        @click.prevent.stop="emits('toggle')"
        class="flex h-8 items-center whitespace-nowrap rounded-full px-3 text-xs shadow-md transition"
        :class="product.is_back_in_stock ? 'bg-green-50 text-green-700 hover:bg-green-100' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'">
        {{ product.is_back_in_stock ? ctrans("Back in stock alert on") : ctrans("Notify me when back in stock") }}
    </button>
</template>
