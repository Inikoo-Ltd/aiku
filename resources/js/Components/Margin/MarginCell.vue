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
    margin: {
        margin_pct: number | null
        profit_amount: number | null
        margin_is_estimated: boolean
        margin_no_cost: boolean
    } | null
    currencyCode?: string
}>()

const locale = inject("locale", aikuLocaleStructure)
</script>

<template>
    <div v-if="margin" class="text-right tabular-nums">
        <span
            v-if="margin.margin_no_cost"
            v-tooltip="trans('No cost data for this product, margin can not be shown. Set the supplier cost on its SKUs to fix this.')"
            class="text-yellow-500">
            <FontAwesomeIcon icon="fas fa-exclamation-triangle" fixed-width aria-hidden="true" />
        </span>
        <template v-else-if="margin.margin_pct !== null">
            <span
                :class="margin.margin_pct < 0 ? 'text-red-500' : ''"
                v-tooltip="margin.margin_is_estimated ? trans('Estimated from current cost, not yet picked') : undefined">
                {{ margin.margin_pct }}%
            </span>
            <span class="text-xs opacity-60 ml-1"><FontAwesomeIcon icon="fal fa-sack-dollar" fixed-width aria-hidden="true" class="mr-0.5" />{{ locale.currencyFormat(currencyCode || "", margin.profit_amount || 0) }}</span>
        </template>
    </div>
</template>
