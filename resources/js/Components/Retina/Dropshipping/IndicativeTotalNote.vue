<script setup lang="ts">
import { computed, inject } from "vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
import { ctrans } from "@/Composables/useTrans"
import { indicativeAmount } from "@/Composables/useDisplayCurrency"

const props = defineProps<{
    currency?: string | null
    total: number | string | null | undefined
}>()

const locale = inject("locale", aikuLocaleStructure)
const layout = inject("layout", retinaLayoutStructure)

const indicativeTotal = computed(() => {
    const displayCurrency = layout?.retina?.display_currency
    const value = indicativeAmount(displayCurrency, layout?.retina?.currency?.code, props.currency, props.total)

    return value === null ? null : locale.currencyFormatUnitCost(displayCurrency.code, value)
})
</script>

<template>
    <div v-if="indicativeTotal" class="mt-2 border-t border-gray-200 pt-2 text-sm">
        <div class="flex justify-between gap-x-4 text-gray-500">
            <span>{{ ctrans("Total in :currency (indicative)", { currency: layout.retina.display_currency.code }) }}</span>
            <span class="whitespace-nowrap">≈ {{ indicativeTotal }}</span>
        </div>
        <div class="mt-1 font-medium text-gray-700">
            {{ ctrans("The order total is :amount, charged in :currency. Amounts in :display_currency are indicative, at the daily exchange rate.", { amount: locale.currencyFormat(currency ?? "", total ?? 0), currency: currency ?? "", display_currency: layout.retina.display_currency.code }) }}
        </div>
    </div>
</template>
