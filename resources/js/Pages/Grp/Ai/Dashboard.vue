<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 30 Sep 2026 12:45:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import PageHeading from "@/Components/Headings/PageHeading.vue"
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
        spent_today: number
        spent_week: number
        spent_month: number
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
}>()

const usd = (value: number) => value > 0 && value < 0.01 ? "<$0.01" : "$" + value.toFixed(2)

const maxDailyCost = computed(() => Math.max(...props.daily.map(d => d.cost), 0.000001))

const totals = computed(() => props.features.reduce((sum, feature) => ({
    today: sum.today + feature.today,
    week: sum.week + feature.week,
    month: sum.month + feature.month,
    year: sum.year + feature.year,
    calls_month: sum.calls_month + feature.calls_month,
    tokens_month: sum.tokens_month + feature.tokens_month,
}), { today: 0, week: 0, month: 0, year: 0, calls_month: 0, tokens_month: 0 }))
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="space-y-6 p-4 text-sm text-gray-700">
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

        <div v-if="!balance" class="rounded-lg border border-gray-200 p-4 text-gray-500">
            {{ ctrans("OpenRouter balance not available: the key is not set or OpenRouter did not answer.") }}
        </div>

        <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            <div class="rounded-lg border p-4" :class="balance.is_low ? 'border-red-300' : 'border-gray-200'">
                <div class="text-xs text-gray-500">{{ ctrans("Left to spend") }}</div>
                <div class="mt-1 text-sm font-semibold tabular-nums" :class="balance.is_low ? 'text-red-700' : 'text-gray-900'">{{ usd(balance.left) }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 p-4">
                <div class="text-xs text-gray-500">{{ ctrans("Account credit") }}</div>
                <div class="mt-1 font-medium tabular-nums">{{ usd(balance.credits_left) }} <span class="text-xs font-normal text-gray-500">/ {{ usd(balance.credits_total) }}</span></div>
            </div>
            <div v-if="balance.key_limit !== null" class="rounded-lg border border-gray-200 p-4">
                <div class="text-xs text-gray-500">{{ ctrans("Key limit") }} <span v-if="balance.key_limit_reset">({{ balance.key_limit_reset }})</span></div>
                <div class="mt-1 font-medium tabular-nums">{{ usd(balance.key_limit_remaining ?? 0) }} <span class="text-xs font-normal text-gray-500">/ {{ usd(balance.key_limit) }}</span></div>
            </div>
            <div class="rounded-lg border border-gray-200 p-4">
                <div class="text-xs text-gray-500">{{ ctrans("Spent today") }}</div>
                <div class="mt-1 font-medium tabular-nums">{{ usd(balance.spent_today) }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 p-4">
                <div class="text-xs text-gray-500">{{ ctrans("Spent this month") }}</div>
                <div class="mt-1 font-medium tabular-nums">{{ usd(balance.spent_month) }}</div>
            </div>
        </div>

        <div v-if="daily.length" class="max-w-3xl rounded-lg border border-gray-200 p-4">
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
                    <tr v-for="feature in features" :key="feature.feature">
                        <td class="px-3 py-2">{{ feature.label }}</td>
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
