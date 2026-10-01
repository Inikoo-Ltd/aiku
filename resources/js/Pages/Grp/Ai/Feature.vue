<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 30 Sep 2026 15:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { usd } from "@/Composables/formatUsd"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import AiDailySpend from "@/Components/Ai/AiDailySpend.vue"
import AiModelSpend from "@/Components/Ai/AiModelSpend.vue"
import AiLiveBadge from "@/Components/Ai/AiLiveBadge.vue"
import { useLiveAiUsage } from "@/Composables/useLiveAiUsage"
import { PageHeadingTypes } from "@/types/PageHeading"
import { faRobot } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"

library.add(faRobot)

defineProps<{
    title: string
    pageHead: PageHeadingTypes
    description: string | null
    spend: { today: number, week: number, month: number, year: number, calls_month: number, tokens_month: number } | null
    daily: { day: string, cost: number, calls: number }[]
    models: { model: string, label: string, cost: number, calls: number, tokens: number }[]
    calls: { created_at: string, model: string, provider: string, prompt_tokens: number, completion_tokens: number, cost: number | null }[]
}>()

const callTime = (value: string) => new Date(value).toLocaleString()

const callCost = (value: number | null) => value === null ? "—" : "$" + value.toFixed(6)

const { lastUpdate, isLive } = useLiveAiUsage(["spend", "daily", "models", "calls"])
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="space-y-6 p-4 text-sm text-gray-700">
        <AiLiveBadge :is-live="isLive" :last-update="lastUpdate" />

        <p v-if="description" class="max-w-2xl text-gray-500">{{ description }}</p>

        <div v-if="spend" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            <div class="rounded-lg border border-gray-200 p-4">
                <div class="text-xs text-gray-500">{{ ctrans("Today") }}</div>
                <div class="mt-1 font-medium tabular-nums">{{ usd(spend.today) }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 p-4">
                <div class="text-xs text-gray-500">{{ ctrans("This week") }}</div>
                <div class="mt-1 font-medium tabular-nums">{{ usd(spend.week) }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 p-4">
                <div class="text-xs text-gray-500">{{ ctrans("This month") }}</div>
                <div class="mt-1 text-sm font-semibold tabular-nums text-gray-900">{{ usd(spend.month) }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 p-4">
                <div class="text-xs text-gray-500">{{ ctrans("This year") }}</div>
                <div class="mt-1 font-medium tabular-nums">{{ usd(spend.year) }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 p-4">
                <div class="text-xs text-gray-500">{{ ctrans("Calls this month") }}</div>
                <div class="mt-1 font-medium tabular-nums">{{ spend.calls_month.toLocaleString() }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 p-4">
                <div class="text-xs text-gray-500">{{ ctrans("Tokens this month") }}</div>
                <div class="mt-1 font-medium tabular-nums">{{ spend.tokens_month.toLocaleString() }}</div>
            </div>
        </div>

        <AiDailySpend v-if="daily.length" :daily="daily" />

        <AiModelSpend v-if="models.length" :models="models" />

        <div class="max-w-3xl overflow-x-auto rounded-lg border border-gray-200">
            <table class="min-w-full text-xs">
                <thead class="bg-gray-50 text-gray-600">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium">{{ ctrans("Latest calls") }}</th>
                        <th class="px-3 py-2 text-left font-medium">{{ ctrans("Model") }}</th>
                        <th class="px-3 py-2 text-right font-medium">{{ ctrans("Tokens in") }}</th>
                        <th class="px-3 py-2 text-right font-medium">{{ ctrans("Tokens out") }}</th>
                        <th class="px-3 py-2 text-right font-medium">{{ ctrans("Cost") }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="(call, index) in calls" :key="index">
                        <td class="whitespace-nowrap px-3 py-1.5 tabular-nums">{{ callTime(call.created_at) }}</td>
                        <td class="px-3 py-1.5">{{ call.model }} <span v-if="call.provider !== 'openrouter'" class="text-gray-500">({{ call.provider }})</span></td>
                        <td class="px-3 py-1.5 text-right tabular-nums">{{ call.prompt_tokens.toLocaleString() }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums">{{ call.completion_tokens.toLocaleString() }}</td>
                        <td class="px-3 py-1.5 text-right tabular-nums">{{ callCost(call.cost) }}</td>
                    </tr>
                    <tr v-if="!calls.length">
                        <td colspan="5" class="px-3 py-4 text-center text-gray-500">{{ ctrans("No calls recorded yet") }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
