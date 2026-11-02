<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 01 Nov 2026, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script lang="ts">
export interface ClaudeUsage {
    percent_used: number
    week_gone_percent: number
    resets_at: string
    sampled_at: string
    runs_out_early_seconds: number | null
}

export const span = (seconds: number) => {
    const minutes = Math.max(0, Math.round(seconds / 60))
    const days = Math.floor(minutes / 1440)
    const hours = Math.floor((minutes % 1440) / 60)

    return days ? `${days}d ${hours}h` : hours ? `${hours}h ${minutes % 60}m` : `${minutes}m`
}

export const isReset = (usage: ClaudeUsage) => Date.parse(usage.resets_at) < Date.now()

export const usageClass = (usage: ClaudeUsage | null) =>
    !usage ? "text-gray-300" : isReset(usage) ? "text-gray-400" : usage.percent_used >= 90 ? "text-red-600" : usage.runs_out_early_seconds !== null ? "text-amber-600" : "text-gray-800"
</script>

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"

defineProps<{
    usage: Record<string, ClaudeUsage>
}>()

const barClass = (usage: ClaudeUsage) =>
    isReset(usage) ? "bg-gray-300" : usage.percent_used >= 90 ? "bg-red-500" : usage.runs_out_early_seconds !== null ? "bg-amber-500" : "bg-green-500"

const forecast = (usage: ClaudeUsage) =>
    isReset(usage) ? ctrans("Reset since the last reading")
        : usage.percent_used >= 100 ? ctrans("Used up")
        : usage.runs_out_early_seconds !== null ? ctrans("Runs out :span before reset", { span: span(usage.runs_out_early_seconds) })
        : ctrans("Lasts the week")

const timeFormat = (iso: string) => new Date(iso).toLocaleString(undefined, { weekday: "short", hour: "2-digit", minute: "2-digit" })
</script>

<template>
    <div class="rounded-xl border border-gray-200 bg-white p-4 text-sm shadow-sm">
        <div class="mb-3 flex items-baseline justify-between gap-2">
            <div class="font-semibold text-gray-800">{{ ctrans("Claude accounts: weekly usage") }}</div>
            <Link :href="route('grp.chat.ai.dashboard')" class="text-xs text-gray-500 hover:text-gray-700 hover:underline">{{ ctrans("Customer service runs") }}</Link>
        </div>
        <div v-if="!Object.keys(usage).length" class="text-xs text-gray-500">{{ ctrans("No usage reading in the last two weeks") }}</div>
        <div v-for="(row, account) in usage" :key="account" class="py-1.5">
            <div class="flex items-baseline justify-between gap-3">
                <span class="truncate text-gray-800">{{ account }}</span>
                <span class="whitespace-nowrap tabular-nums" :class="usageClass(row)">
                    {{ row.percent_used }}% <span class="text-xs text-gray-400">/ {{ row.week_gone_percent.toFixed(1) }}%</span>
                </span>
            </div>
            <div class="relative mt-1 h-1.5 rounded-full bg-gray-100" v-tooltip="ctrans('Bar: share of the weekly limit used. Line: share of the week gone')">
                <div class="h-full rounded-full" :class="barClass(row)" :style="{ width: Math.min(100, row.percent_used) + '%' }" />
                <div class="absolute -top-0.5 h-2.5 w-0.5 bg-gray-700" :style="{ left: row.week_gone_percent + '%' }" />
            </div>
            <div class="mt-0.5 flex justify-between gap-3 text-xs text-gray-400">
                <span :class="usageClass(row)">{{ forecast(row) }}</span>
                <span class="whitespace-nowrap">{{ ctrans("resets :at · read :age ago", { at: timeFormat(row.resets_at), age: span((Date.now() - Date.parse(row.sampled_at)) / 1000) }) }}</span>
            </div>
        </div>
    </div>
</template>
