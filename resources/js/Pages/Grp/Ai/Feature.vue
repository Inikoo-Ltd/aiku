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
import { useFormatTime } from "@/Composables/useFormatTime"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
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
    calls: {}
}>()

const callCost = (value: number | null) => value === null ? "—" : "$" + value.toFixed(6)

const { lastUpdate, isLive } = useLiveAiUsage(["spend", "daily", "models", "calls", "queryBuilderProps"])
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

        <Table :resource="calls">
            <template #cell(created_at)="{ item }">
                <span class="whitespace-nowrap tabular-nums">{{ useFormatTime(item.created_at, { formatTime: "aiku" }) }} <span class="text-gray-400">{{ useFormatTime(item.created_at, { formatTime: "HH:mm:ss" }) }}</span></span>
            </template>
            <template #cell(model)="{ item }">
                {{ item.model }} <span v-if="item.provider !== 'openrouter'" class="text-gray-500">({{ item.provider }})</span>
            </template>
            <template #cell(prompt_tokens)="{ item }">{{ item.prompt_tokens.toLocaleString() }}</template>
            <template #cell(completion_tokens)="{ item }">{{ item.completion_tokens.toLocaleString() }}</template>
            <template #cell(cost)="{ item }">{{ callCost(item.cost) }}</template>
        </Table>
    </div>
</template>
