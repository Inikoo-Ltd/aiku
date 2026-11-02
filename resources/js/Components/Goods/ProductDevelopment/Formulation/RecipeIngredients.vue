<!--
  - Author Louis Perez
  - Created on 26-10-2026-15h-30m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { computed } from "vue"
import { ctrans } from "@/Composables/useTrans"

export interface Ingredient {
    code: string
    description: string
    unit: string
    quantity: number
    percent: number | null
}

const props = defineProps<{
    ingredients: Ingredient[]
    previous?: Ingredient[] | null
}>()

const unitLabels: Record<string, string> = { kilogram: "kg", liter: "L", unit: "unit", pack: "pack", carton: "carton" }

const key = (line: Ingredient) => `${line.code}|${line.unit}`
const previousByKey = computed(() => new Map((props.previous ?? []).map((line) => [key(line), line])))
const removed = computed(() => (props.previous ?? []).filter((line) => !props.ingredients.some((current) => key(current) === key(line))))

function change(line: Ingredient): "new" | "changed" | null {
    if (!props.previous) {
        return null
    }
    const before = previousByKey.value.get(key(line))
    if (!before) {
        return "new"
    }

    return Math.abs(before.quantity - line.quantity) > 0.000001 ? "changed" : null
}

const formatQuantity = (quantity: number) => Number(quantity.toFixed(6)).toString()
</script>

<template>
    <div class="overflow-x-auto rounded-md border border-gray-200">
        <table class="w-full text-sm">
            <thead class="whitespace-nowrap bg-gray-50 text-left text-xs text-gray-500">
                <tr>
                    <th class="px-3 py-1.5 font-medium">{{ ctrans("Ingredient") }}</th>
                    <th class="px-3 py-1.5 text-right font-medium">{{ ctrans("Per unit") }}</th>
                    <th class="px-3 py-1.5 text-right font-medium">%</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <tr v-for="line in ingredients" :key="key(line)" :class="change(line) === 'new' ? 'bg-green-50/60' : change(line) === 'changed' ? 'bg-amber-50/60' : ''">
                    <td class="px-3 py-1.5">
                        <span class="whitespace-nowrap font-medium text-gray-900">{{ line.code }}</span>
                        <span class="ml-1.5 text-gray-600">{{ line.description }}</span>
                        <span v-if="change(line) === 'new'" class="ml-1.5 rounded bg-green-100 px-1 text-[11px] text-green-800">{{ ctrans("new") }}</span>
                    </td>
                    <td class="whitespace-nowrap px-3 py-1.5 text-right tabular-nums text-gray-800">
                        <span v-if="change(line) === 'changed'" class="mr-1.5 text-xs text-gray-400 line-through">{{ formatQuantity(previousByKey.get(key(line))!.quantity) }}</span>
                        {{ formatQuantity(line.quantity) }} {{ unitLabels[line.unit] ?? line.unit }}
                    </td>
                    <td class="whitespace-nowrap px-3 py-1.5 text-right tabular-nums text-gray-700">{{ line.percent !== null ? `${line.percent}%` : "—" }}</td>
                </tr>
                <tr v-for="line in removed" :key="`removed-${key(line)}`" class="bg-red-50/60 text-gray-500">
                    <td class="px-3 py-1.5 line-through">
                        <span class="whitespace-nowrap">{{ line.code }}</span>
                        <span class="ml-1.5">{{ line.description }}</span>
                    </td>
                    <td class="whitespace-nowrap px-3 py-1.5 text-right tabular-nums line-through">{{ formatQuantity(line.quantity) }} {{ unitLabels[line.unit] ?? line.unit }}</td>
                    <td class="px-3 py-1.5 text-right text-xs">{{ ctrans("removed") }}</td>
                </tr>
                <tr v-if="!ingredients.length && !removed.length">
                    <td colspan="3" class="px-3 py-3 text-center text-xs text-gray-400">{{ ctrans("No ingredients yet") }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
