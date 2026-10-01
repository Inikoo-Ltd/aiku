<script setup lang="ts">
import { computed } from "vue"
import { ctrans } from "@/Composables/useTrans"
import { usd } from "@/Composables/formatUsd"

const props = defineProps<{
    daily: { day: string, cost: number, calls: number }[]
}>()

const maxDailyCost = computed(() => Math.max(...props.daily.map(d => d.cost), 0.000001))
</script>

<template>
    <div class="max-w-3xl rounded-lg border border-gray-200 p-4">
        <div class="flex items-baseline justify-between text-xs text-gray-500">
            <span>{{ ctrans("Daily spend, last 30 days") }}</span>
            <span class="tabular-nums">{{ ctrans("peak") }} {{ usd(maxDailyCost) }}</span>
        </div>
        <div class="mt-3 flex h-24 items-end gap-0.5">
            <div v-for="day in daily" :key="day.day" class="flex-1 rounded-t bg-indigo-400 hover:bg-indigo-600"
                :style="{ height: Math.max((day.cost / maxDailyCost) * 100, 2) + '%' }"
                :title="`${day.day}: ${usd(day.cost)}, ${day.calls} ${ctrans('calls')}`" />
        </div>
    </div>
</template>
