<script setup lang="ts">
import { inject } from "vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { trans } from "laravel-vue-i18n"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faExclamationTriangle } from "@fas"
import { faSackDollar } from "@fal"

library.add(faExclamationTriangle, faSackDollar)

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
}>()

const locale = inject("locale", aikuLocaleStructure)
</script>

<template>
    <div v-if="summary" class="flex items-center gap-2 px-3 py-2 rounded border border-gray-200 bg-gray-50 text-sm w-fit">
        <span class="opacity-70">{{ trans("Margin") }}:</span>
        <span
            class="font-medium tabular-nums"
            :class="{ 'text-red-600': summary.margin_status === 'danger', 'text-amber-600': summary.margin_status === 'warning' }"
            v-tooltip="summary.margin_status === 'danger' ? trans('Below the :pct% break-even margin set for this organisation, likely unprofitable after running costs', { pct: String(summary.break_even_pct) }) : summary.margin_status === 'warning' ? trans('Thin margin, careful with further discounts') : undefined">
            <template v-if="summary.is_estimated">~</template>{{ summary.margin_pct }}%
        </span>
        <span
            class="tabular-nums opacity-70 cursor-help"
            v-tooltip="trans(':amount is the item profit only: what the items sold for minus what the stock cost. HR, rent, shipping, marketing, payment fees and all other expenses still need to be subtracted, the real profit is much lower.', { amount: locale.currencyFormat(summary.currency_code, summary.profit_amount) })">
            <FontAwesomeIcon icon="fal fa-sack-dollar" fixed-width aria-hidden="true" class="mr-0.5" />{{ locale.currencyFormat(summary.currency_code, summary.profit_amount) }}</span>
        <span
            v-if="summary.before_discounts"
            v-tooltip="trans('Margin before discounts')"
            class="text-xs opacity-60 tabular-nums">
            ({{ trans("before discounts") }} {{ summary.before_discounts.margin_pct }}%)
        </span>
        <span
            v-if="summary.lines_without_cost > 0"
            v-tooltip="trans(':count lines have no cost data and are excluded from this margin. Set supplier costs on their SKUs to fix this.', { count: String(summary.lines_without_cost) })"
            class="text-yellow-500">
            <FontAwesomeIcon icon="fas fa-exclamation-triangle" fixed-width aria-hidden="true" />
        </span>
        <span
            v-else-if="summary.is_estimated"
            v-tooltip="trans('Partly estimated from current costs, some lines are not yet picked')"
            class="opacity-60 text-xs">{{ trans("estimated") }}</span>
    </div>
</template>
