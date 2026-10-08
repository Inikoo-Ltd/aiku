<script setup lang="ts">
import { inject } from "vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { ctrans } from "@/Composables/useTrans"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faExclamationTriangle } from "@fas"
import { faSackDollar, faChartLine } from "@fal"

library.add(faExclamationTriangle, faSackDollar, faChartLine)

defineProps<{
    summary: {
        profit_amount: number
        margin_pct: number
        before_discounts: { margin_pct: number; profit_amount: number } | null
        break_even_pct: number
        is_below_break_even: boolean
        margin_status: 'danger' | 'warning' | 'ok'
        is_estimated: boolean
        lines_without_cost: number
        currency_code: string
    } | null
    inline?: boolean
}>()

const locale = inject("locale", aikuLocaleStructure)
</script>

<template>
    <dl v-if="summary && inline" class="flex items-center w-fit pr-3 flex-none gap-x-1.5">
        <dt class="flex-none">
            <FontAwesomeIcon v-tooltip="ctrans('Margin')" icon="fal fa-chart-line" fixed-width aria-hidden="true" class="text-gray-500" />
        </dt>
        <dd class="flex flex-wrap items-center gap-x-1.5 text-gray-500">
            <span
                class="font-medium tabular-nums"
                :class="summary.margin_status === 'danger' ? 'text-red-600' : summary.margin_status === 'warning' ? 'text-amber-600' : 'text-gray-700'"
                v-tooltip="summary.margin_status === 'danger' ? ctrans('Below the :pct% break-even margin set for this organisation, likely unprofitable after running costs', { pct: String(summary.break_even_pct) }) : summary.margin_status === 'warning' ? ctrans('Thin margin, careful with further discounts') : undefined">
                {{ summary.margin_pct }}% {{ ctrans("margin") }}
            </span>
            <span
                class="tabular-nums cursor-help"
                v-tooltip="ctrans(':amount is the item profit only: what the items sold for minus what the stock cost. HR, rent, shipping, marketing, payment fees and all other expenses still need to be subtracted, the real profit is much lower.', { amount: locale.currencyFormat(summary.currency_code, summary.profit_amount) })">
                · {{ locale.currencyFormat(summary.currency_code, summary.profit_amount) }}
            </span>
            <span v-if="summary.before_discounts" class="text-xs tabular-nums">
                ({{ ctrans("before discounts") }} {{ summary.before_discounts.margin_pct }}%)
            </span>
            <FontAwesomeIcon
                v-if="summary.lines_without_cost > 0"
                v-tooltip="ctrans(':count lines have no cost data and are excluded from this margin. Set supplier costs on their SKUs to fix this.', { count: String(summary.lines_without_cost) })"
                icon="fas fa-exclamation-triangle"
                fixed-width
                aria-hidden="true"
                class="text-xs text-amber-500" />
            <span v-else-if="summary.is_estimated" v-tooltip="ctrans('Partly estimated from current costs, some lines are not yet picked')" class="text-xs">
                {{ ctrans("estimated") }}
            </span>
        </dd>
    </dl>

    <div v-else-if="summary" class="flex items-center gap-2 px-3 py-2 rounded border border-gray-200 bg-gray-50 text-sm w-fit">
        <span class="opacity-70">{{ ctrans("Margin") }}:</span>
        <span
            class="font-medium tabular-nums"
            :class="{ 'text-red-600': summary.margin_status === 'danger', 'text-amber-600': summary.margin_status === 'warning' }"
            v-tooltip="summary.margin_status === 'danger' ? ctrans('Below the :pct% break-even margin set for this organisation, likely unprofitable after running costs', { pct: String(summary.break_even_pct) }) : summary.margin_status === 'warning' ? ctrans('Thin margin, careful with further discounts') : undefined">
            {{ summary.margin_pct }}%
        </span>
        <span
            class="tabular-nums opacity-70 cursor-help"
            v-tooltip="ctrans(':amount is the item profit only: what the items sold for minus what the stock cost. HR, rent, shipping, marketing, payment fees and all other expenses still need to be subtracted, the real profit is much lower.', { amount: locale.currencyFormat(summary.currency_code, summary.profit_amount) })">
            <FontAwesomeIcon icon="fal fa-sack-dollar" fixed-width aria-hidden="true" class="mr-0.5" />{{ locale.currencyFormat(summary.currency_code, summary.profit_amount) }}</span>
        <span
            v-if="summary.before_discounts"
            v-tooltip="ctrans('Margin before discounts')"
            class="text-xs opacity-60 tabular-nums">
            ({{ ctrans("before discounts") }} {{ summary.before_discounts.margin_pct }}%)
        </span>
        <span
            v-if="summary.lines_without_cost > 0"
            v-tooltip="ctrans(':count lines have no cost data and are excluded from this margin. Set supplier costs on their SKUs to fix this.', { count: String(summary.lines_without_cost) })"
            class="text-yellow-500">
            <FontAwesomeIcon icon="fas fa-exclamation-triangle" fixed-width aria-hidden="true" />
        </span>
        <span
            v-else-if="summary.is_estimated"
            v-tooltip="ctrans('Partly estimated from current costs, some lines are not yet picked')"
            class="opacity-60 text-xs">{{ ctrans("estimated") }}</span>
    </div>
</template>
