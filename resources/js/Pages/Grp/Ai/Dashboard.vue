<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 30 Sep 2026 12:45:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { usd } from "@/Composables/formatUsd"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import AiDailySpend from "@/Components/Ai/AiDailySpend.vue"
import AiModelSpend from "@/Components/Ai/AiModelSpend.vue"
import AiLiveBadge from "@/Components/Ai/AiLiveBadge.vue"
import ClaudeAccountUsage, { ClaudeUsage } from "@/Components/Ai/ClaudeAccountUsage.vue"
import { useLiveAiUsage } from "@/Composables/useLiveAiUsage"
import { PageHeadingTypes } from "@/types/PageHeading"
import { faRobot, faExclamationTriangle } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { computed, inject } from "vue"
import { layoutStructure } from "@/Composables/useLayoutStructure"

library.add(faRobot, faExclamationTriangle)

const layout = inject("layout", layoutStructure)
const primaryColor = computed(() => layout.app.theme[4])

const leftPct = (left: number, total: number) => total > 0 ? Math.min(100, Math.max(0, (left / total) * 100)) : 0

const props = defineProps<{
    claudeUsage: Record<string, ClaudeUsage>
    title: string
    pageHead: PageHeadingTypes
    balance: {
        credits_total: number
        credits_used: number
        credits_left: number
        key_limit: number | null
        key_limit_remaining: number | null
        key_limit_reset: string | null
        left: number
        low_credit_alert: number
        is_low: boolean
    } | null
    features: {}
    totals: { today: number, week: number, month: number, year: number, calls_month: number, tokens_month: number }
    daily: { day: string, cost: number, calls: number }[]
    models: { model: string, label: string, cost: number, calls: number, tokens: number }[]
}>()

const moneyColumns = ["today", "week", "month", "year"] as const

const { lastUpdate, isLive } = useLiveAiUsage(["features", "totals", "queryBuilderProps", "daily", "models", "balance"])
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="space-y-6 p-4 text-sm text-gray-700">
        <AiLiveBadge :is-live="isLive" :last-update="lastUpdate" />

        <div v-if="balance?.is_low" class="flex items-start gap-3 rounded-lg border border-red-300 bg-red-50 p-4 text-red-800">
            <FontAwesomeIcon icon="fal fa-exclamation-triangle" class="mt-0.5" fixed-width aria-hidden="true" />
            <div>
                <div class="font-semibold">{{ ctrans("AI credit is running low") }}</div>
                <div>
                    {{ ctrans("Only :left left to spend (alert below :alert). When it reaches $0 every AI feature stops.", { left: usd(balance.left), alert: usd(balance.low_credit_alert) }) }}
                    <a href="https://openrouter.ai/settings/credits" target="_blank" rel="noopener" class="underline">{{ ctrans("Top up") }}</a>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
            <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="absolute inset-y-0 left-0 w-1" :style="{ backgroundColor: primaryColor }" />
                <div class="text-xs text-gray-500">{{ ctrans("Spent today") }}</div>
                <div class="mt-1 text-xl font-semibold tabular-nums text-gray-900">{{ usd(totals.today) }}</div>
            </div>
            <div class="relative overflow-hidden rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="absolute inset-y-0 left-0 w-1" :style="{ backgroundColor: primaryColor }" />
                <div class="text-xs text-gray-500">{{ ctrans("Spent this month") }}</div>
                <div class="mt-1 text-xl font-semibold tabular-nums text-gray-900">{{ usd(totals.month) }}</div>
            </div>
            <template v-if="balance">
                <div class="rounded-xl border bg-white p-4 shadow-sm" :class="balance.is_low ? 'border-red-300' : 'border-gray-200'">
                    <div class="text-xs text-gray-500">{{ ctrans("OpenRouter left to spend") }}</div>
                    <div class="mt-1 text-xl font-semibold tabular-nums" :class="balance.is_low ? 'text-red-700' : 'text-gray-900'">{{ usd(balance.left) }}</div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="text-xs text-gray-500">{{ ctrans("OpenRouter credit") }}</div>
                    <div class="mt-1 text-xl font-semibold tabular-nums text-gray-900">{{ usd(balance.credits_left) }} <span class="text-xs font-normal text-gray-500">/ {{ usd(balance.credits_total) }}</span></div>
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-gray-100">
                        <div class="h-full rounded-full" :style="{ width: leftPct(balance.credits_left, balance.credits_total) + '%', backgroundColor: primaryColor }" />
                    </div>
                </div>
                <div v-if="balance.key_limit !== null" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="text-xs text-gray-500">{{ ctrans("Key limit") }} <span v-if="balance.key_limit_reset">({{ balance.key_limit_reset }})</span></div>
                    <div class="mt-1 text-xl font-semibold tabular-nums text-gray-900">{{ usd(balance.key_limit_remaining ?? 0) }} <span class="text-xs font-normal text-gray-500">/ {{ usd(balance.key_limit) }}</span></div>
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-gray-100">
                        <div class="h-full rounded-full" :style="{ width: leftPct(balance.key_limit_remaining ?? 0, balance.key_limit) + '%', backgroundColor: primaryColor }" />
                    </div>
                </div>
            </template>
            <div v-else class="col-span-2 rounded-xl border border-gray-200 bg-white p-4 text-xs text-gray-500 shadow-sm sm:col-span-3">
                {{ ctrans("OpenRouter balance not available: the key is not set or OpenRouter did not answer.") }}
            </div>
        </div>
        <p class="-mt-3 text-xs text-gray-500">{{ ctrans("Spend includes what OpenAI charges on our own OpenAI key (BYOK); the OpenRouter boxes only show OpenRouter credit.") }}</p>

        <ClaudeAccountUsage :usage="claudeUsage" class="max-w-xl" />

        <div class="grid gap-6 xl:grid-cols-2">
            <AiDailySpend v-if="daily.length" :daily="daily" />
            <AiModelSpend v-if="models.length" :models="models" />
        </div>

        <Table :resource="features">
            <template #cell(label)="{ item }">
                <Link :href="route('grp.ai.features.show', { feature: item.feature })" class="primaryLink">{{ item.label }}</Link>
            </template>
            <template v-for="column in moneyColumns" :key="column" #[`cell(${column})`]="{ item }">
                {{ usd(item[column]) }}
            </template>
            <template v-for="column in moneyColumns" :key="column" #[`footerRows-cell(${column})`]="{ item }">
                {{ usd(item[column]) }}
            </template>
            <template #cell(calls_month)="{ item }">{{ item.calls_month.toLocaleString() }}</template>
            <template #cell(tokens_month)="{ item }">{{ item.tokens_month.toLocaleString() }}</template>
            <template #footerRows-cell(calls_month)="{ item }">{{ item.calls_month.toLocaleString() }}</template>
            <template #footerRows-cell(tokens_month)="{ item }">{{ item.tokens_month.toLocaleString() }}</template>
        </Table>
    </div>
</template>
