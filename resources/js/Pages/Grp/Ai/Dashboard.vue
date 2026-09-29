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
import AiDailySpend from "@/Components/Ai/AiDailySpend.vue"
import AiModelSpend from "@/Components/Ai/AiModelSpend.vue"
import AiLiveBadge from "@/Components/Ai/AiLiveBadge.vue"
import { useLiveAiUsage } from "@/Composables/useLiveAiUsage"
import { PageHeadingTypes } from "@/types/PageHeading"
import { faRobot, faExclamationTriangle } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { computed } from "vue"

library.add(faRobot, faExclamationTriangle)

const props = defineProps<{
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
    features: {
        feature: string
        label: string
        today: number
        week: number
        month: number
        year: number
        calls_month: number
        tokens_month: number
    }[]
    daily: { day: string, cost: number, calls: number }[]
    models: { model: string, label: string, cost: number, calls: number, tokens: number }[]
}>()

const totals = computed(() => props.features.reduce((sum, feature) => ({
    today: sum.today + feature.today,
    week: sum.week + feature.week,
    month: sum.month + feature.month,
    year: sum.year + feature.year,
    calls_month: sum.calls_month + feature.calls_month,
    tokens_month: sum.tokens_month + feature.tokens_month,
}), { today: 0, week: 0, month: 0, year: 0, calls_month: 0, tokens_month: 0 }))

const { lastUpdate, isLive } = useLiveAiUsage(["features", "daily", "models", "balance"])
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

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            <div class="rounded-lg border border-gray-200 p-4">
                <div class="text-xs text-gray-500">{{ ctrans("Spent today") }}</div>
                <div class="mt-1 font-medium tabular-nums">{{ usd(totals.today) }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 p-4">
                <div class="text-xs text-gray-500">{{ ctrans("Spent this month") }}</div>
                <div class="mt-1 text-sm font-semibold tabular-nums text-gray-900">{{ usd(totals.month) }}</div>
            </div>
            <template v-if="balance">
                <div class="rounded-lg border p-4" :class="balance.is_low ? 'border-red-300' : 'border-gray-200'">
                    <div class="text-xs text-gray-500">{{ ctrans("OpenRouter left to spend") }}</div>
                    <div class="mt-1 font-medium tabular-nums" :class="balance.is_low ? 'text-red-700' : ''">{{ usd(balance.left) }}</div>
                </div>
                <div class="rounded-lg border border-gray-200 p-4">
                    <div class="text-xs text-gray-500">{{ ctrans("OpenRouter credit") }}</div>
                    <div class="mt-1 font-medium tabular-nums">{{ usd(balance.credits_left) }} <span class="text-xs font-normal text-gray-500">/ {{ usd(balance.credits_total) }}</span></div>
                </div>
                <div v-if="balance.key_limit !== null" class="rounded-lg border border-gray-200 p-4">
                    <div class="text-xs text-gray-500">{{ ctrans("Key limit") }} <span v-if="balance.key_limit_reset">({{ balance.key_limit_reset }})</span></div>
                    <div class="mt-1 font-medium tabular-nums">{{ usd(balance.key_limit_remaining ?? 0) }} <span class="text-xs font-normal text-gray-500">/ {{ usd(balance.key_limit) }}</span></div>
                </div>
            </template>
            <div v-else class="col-span-2 rounded-lg border border-gray-200 p-4 text-xs text-gray-500 sm:col-span-3">
                {{ ctrans("OpenRouter balance not available: the key is not set or OpenRouter did not answer.") }}
            </div>
        </div>
        <p class="-mt-4 text-xs text-gray-500">{{ ctrans("Spend includes what OpenAI charges on our own OpenAI key (BYOK); the OpenRouter boxes only show OpenRouter credit.") }}</p>

        <AiDailySpend v-if="daily.length" :daily="daily" />

        <AiModelSpend v-if="models.length" :models="models" />

        <div class="overflow-x-auto rounded-lg border border-gray-200">
            <table class="min-w-full text-xs">
                <thead class="bg-gray-50 text-gray-600">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium">{{ ctrans("Used for") }}</th>
                        <th class="px-3 py-2 text-right font-medium">{{ ctrans("Today") }}</th>
                        <th class="px-3 py-2 text-right font-medium">{{ ctrans("This week") }}</th>
                        <th class="px-3 py-2 text-right font-medium">{{ ctrans("This month") }}</th>
                        <th class="px-3 py-2 text-right font-medium">{{ ctrans("This year") }}</th>
                        <th class="px-3 py-2 text-right font-medium">{{ ctrans("Calls this month") }}</th>
                        <th class="px-3 py-2 text-right font-medium">{{ ctrans("Tokens this month") }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="feature in features" :key="feature.feature" class="hover:bg-gray-50">
                        <td class="px-3 py-2">
                            <Link :href="route('grp.ai.features.show', { feature: feature.feature })" class="text-indigo-600 hover:underline">{{ feature.label }}</Link>
                        </td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ usd(feature.today) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ usd(feature.week) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ usd(feature.month) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ usd(feature.year) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ feature.calls_month.toLocaleString() }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ feature.tokens_month.toLocaleString() }}</td>
                    </tr>
                    <tr v-if="!features.length">
                        <td colspan="7" class="px-3 py-4 text-center text-gray-500">{{ ctrans("No AI calls recorded yet") }}</td>
                    </tr>
                </tbody>
                <tfoot v-if="features.length" class="bg-gray-50 font-medium">
                    <tr>
                        <td class="px-3 py-2">{{ ctrans("Total") }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ usd(totals.today) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ usd(totals.week) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ usd(totals.month) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ usd(totals.year) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ totals.calls_month.toLocaleString() }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ totals.tokens_month.toLocaleString() }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</template>
