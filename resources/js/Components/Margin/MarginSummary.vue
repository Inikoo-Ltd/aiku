<script setup lang="ts">
import { inject } from "vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { trans } from "laravel-vue-i18n"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faExclamationTriangle } from "@fas"

library.add(faExclamationTriangle)

defineProps<{
    summary: {
        profit_amount: number
        margin_pct: number
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
        <span class="font-medium tabular-nums" :class="summary.margin_pct < 0 ? 'text-red-600' : ''">
            <template v-if="summary.is_estimated || summary.lines_without_cost > 0">~</template>{{ summary.margin_pct }}%
        </span>
        <span class="tabular-nums opacity-70">{{ locale.currencyFormat(summary.currency_code, summary.profit_amount) }}</span>
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
