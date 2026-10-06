<script setup lang="ts">
import { computed, inject } from "vue"
import { ctrans } from "@/Composables/useTrans"
import { usd } from "@/Composables/formatUsd"
import { layoutStructure } from "@/Composables/useLayoutStructure"

const props = defineProps<{
    models: { model: string, label: string, cost: number, calls: number, tokens: number }[]
}>()

const layout = inject("layout", layoutStructure)
const primaryColor = computed(() => layout.app.theme[4])

// Theme colour first, then distinct accents so the stacked bar stays readable
const colours = computed(() => [primaryColor.value, "#f87171", "#f59e0b", "#10b981", "#d946ef", "#0ea5e9", "#a3a35c", "#64748b"])

const colour = (index: number) => colours.value[index % colours.value.length]

const total = computed(() => props.models.reduce((sum, model) => sum + model.cost, 0))

const share = (cost: number) => total.value > 0 ? (cost / total.value) * 100 : 0
</script>

<template>
    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <div class="flex items-baseline justify-between">
            <div class="text-sm font-semibold text-gray-800">{{ ctrans("Spend by model") }}</div>
            <div class="text-xs text-gray-500">{{ ctrans("This month") }} · {{ usd(total) }}</div>
        </div>
        <div class="mt-4 flex h-3 gap-px overflow-hidden rounded-full bg-gray-100">
            <div v-for="(model, index) in models" :key="model.model" class="h-full"
                :style="{ width: share(model.cost) + '%', backgroundColor: colour(index) }"
                :title="`${model.label}: ${usd(model.cost)}`" />
        </div>
        <table class="mt-4 w-full text-xs">
            <thead class="text-gray-500">
                <tr class="border-b border-gray-200">
                    <th class="pb-2 text-left font-medium">{{ ctrans("Model") }}</th>
                    <th class="pb-2 text-right font-medium">{{ ctrans("Calls") }}</th>
                    <th class="pb-2 text-right font-medium">{{ ctrans("Tokens") }}</th>
                    <th class="pb-2 text-right font-medium">{{ ctrans("Value") }}</th>
                    <th class="w-40 pb-2 text-right font-medium">{{ ctrans("% of total") }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <tr v-for="(model, index) in models" :key="model.model" class="hover:bg-gray-50">
                    <td class="py-2 text-gray-800">
                        <span class="mr-2 inline-block h-2.5 w-2.5 rounded-full align-middle" :style="{ backgroundColor: colour(index) }" />{{ model.label }}
                    </td>
                    <td class="py-2 text-right tabular-nums text-gray-600">{{ model.calls.toLocaleString() }}</td>
                    <td class="py-2 text-right tabular-nums text-gray-600">{{ model.tokens.toLocaleString() }}</td>
                    <td class="py-2 text-right font-medium tabular-nums text-gray-900">{{ usd(model.cost) }}</td>
                    <td class="py-2">
                        <div class="flex items-center justify-end gap-2">
                            <div class="h-1.5 w-20 overflow-hidden rounded-full bg-gray-100">
                                <div class="h-full rounded-full" :style="{ width: share(model.cost) + '%', backgroundColor: primaryColor }" />
                            </div>
                            <span class="w-12 text-right tabular-nums text-gray-600">{{ share(model.cost).toFixed(1) }}%</span>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
