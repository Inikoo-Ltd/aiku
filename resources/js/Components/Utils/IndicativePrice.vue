<script setup lang="ts">
import { computed, inject } from "vue"
import { useLocaleStore } from "@/Stores/locale"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
import { ctrans } from "@/Composables/useTrans"
import { indicativeAmount } from "@/Composables/useDisplayCurrency"

const props = defineProps<{
    currency?: string | null
    amount: number | string | null | undefined
    charged?: boolean
    rrp?: boolean
}>()

const locale = useLocaleStore()
const layout = inject("layout", retinaLayoutStructure)

const shopAmount = computed(() => props.rrp ? locale.currencyFormatRrp(props.currency ?? "", props.amount ?? 0) : locale.currencyFormat(props.currency ?? "", props.amount ?? 0))

const displayAmount = computed(() => {
    const displayCurrency = layout?.retina?.display_currency
    const value = indicativeAmount(displayCurrency, layout?.retina?.currency?.code, props.currency, props.amount)

    return value === null ? null : locale.currencyFormatUnitCost(displayCurrency.code, value)
})
</script>

<template>
    <span v-if="displayAmount" v-tooltip="charged ? ctrans('Indicative. You are charged :amount', { amount: shopAmount }) : ctrans('Indicative. Actual: :amount', { amount: shopAmount })" class="whitespace-nowrap cursor-help">≈ {{ displayAmount }}</span>
    <span v-else>{{ shopAmount }}</span>
</template>
