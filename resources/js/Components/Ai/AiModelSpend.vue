<script setup lang="ts">
import { computed } from "vue"
import { ctrans } from "@/Composables/useTrans"
import { usd } from "@/Composables/formatUsd"

const props = defineProps<{
    models: { model: string, label: string, cost: number, calls: number, tokens: number }[]
}>()

const colours = ["#10b981", "#f87171", "#a3a35c", "#d946ef", "#6366f1", "#f59e0b", "#0ea5e9", "#64748b"]

const colour = (index: number) => colours[index % colours.length]

const total = computed(() => props.models.reduce((sum, model) => sum + model.cost, 0))

const share = (cost: number) => total.value > 0 ? (cost / total.value) * 100 : 0
</script>

<template>
    <div class="max-w-3xl rounded-lg border border-gray-200 p-4">
        <div class="text-xs text-gray-500">{{ ctrans("Spend by model, this month") }}</div>
        <div class="mt-3 flex h-4 overflow-hidden rounded bg-gray-100">
            <div v-for="(model, index) in models" :key="model.model" class="h-full"
                :style="{ width: share(model.cost) + '%', backgroundColor: colour(index) }"
                :title="`${model.label}: ${usd(model.cost)}`" />
        </div>
        <table class="mt-3 w-full text-xs">
            <thead class="text-gray-600">
                <tr>
                    <th class="py-1 text-left font-medium">{{ ctrans("Model") }}</th>
                    <th class="py-1 text-right font-medium">{{ ctrans("Calls") }}</th>
                    <th class="py-1 text-right font-medium">{{ ctrans("Tokens") }}</th>
                    <th class="py-1 text-right font-medium">{{ ctrans("Value") }}</th>
                    <th class="w-40 py-1 text-right font-medium">{{ ctrans("% of total") }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <tr v-for="(model, index) in models" :key="model.model">
                    <td class="py-1.5">
                        <span class="mr-2 inline-block h-2.5 w-2.5 rounded-full align-middle" :style="{ backgroundColor: colour(index) }" />{{ model.label }}
                    </td>
                    <td class="py-1.5 text-right tabular-nums">{{ model.calls.toLocaleString() }}</td>
                    <td class="py-1.5 text-right tabular-nums">{{ model.tokens.toLocaleString() }}</td>
                    <td class="py-1.5 text-right tabular-nums">{{ usd(model.cost) }}</td>
                    <td class="py-1.5">
                        <div class="flex items-center justify-end gap-2">
                            <div class="h-1.5 w-20 rounded bg-gray-100">
                                <div class="h-1.5 rounded" :style="{ width: share(model.cost) + '%', backgroundColor: colour(index) }" />
                            </div>
                            <span class="w-12 text-right tabular-nums">{{ share(model.cost).toFixed(1) }}%</span>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
