<script setup lang="ts">
import { inject, computed } from "vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { ctrans } from "@/Composables/useTrans"

interface IntervalDataItem {
    raw_value?: number | string
    formatted_value?: string
}

interface IntervalData {
    visitors?: {
        all?: IntervalDataItem
    }
    sales_org_currency_external?: {
        mtd?: IntervalDataItem
        lm?: IntervalDataItem
    }
    orders?: {
        mtd?: IntervalDataItem
        lm?: IntervalDataItem
    }
}

interface ShopBlocks {
    interval_data?: IntervalData
    currency_code?: string
    average_clv?: string
    average_historic_clv?: string
}

const props = defineProps<{
    shopBlocks?: ShopBlocks
}>()

const locale = inject('locale', aikuLocaleStructure)

const getAverageCLV = computed(() => {
    const clv = props.shopBlocks?.average_clv
    if (!clv || clv === '0') {
        return null
    }

    return locale.currencyFormat(
        props.shopBlocks?.currency_code,
        parseFloat(clv)
    )
})

const getHistoricCLV = computed(() => {
    const historicClv = props.shopBlocks?.average_historic_clv
    if (!historicClv || historicClv === '0') {
        return null
    }

    return locale.currencyFormat(
        props.shopBlocks?.currency_code,
        parseFloat(historicClv)
    )
})
</script>

<template>
    <div v-if="props.shopBlocks?.interval_data" class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4 px-4 py-4">
        <!-- Average CLV -->
        <div v-if="getAverageCLV !== null" class="flex items-center gap-4 p-4 bg-gray-50 border shadow-sm rounded-lg">
            <div class="text-sm w-full">
                <p class="text-lg font-bold mb-1">{{ ctrans('Average CLV') }}</p>
                <span class="text-2xl font-bold">
                    {{ getAverageCLV }}
                </span>
                <p class="text-xs text-gray-500 mt-1">{{ctrans('Customer Lifetime Value')}}</p>
            </div>
        </div>

        <!-- Historic CLV -->
        <div v-if="getHistoricCLV !== null" class="flex items-center gap-4 p-4 bg-gray-50 border shadow-sm rounded-lg">
            <div class="text-sm w-full">
                <p class="text-lg font-bold mb-1">{{ ctrans('Historic CLV')}}</p>
                <span class="text-2xl font-bold">
                    {{ getHistoricCLV }}
                </span>
                <p class="text-xs text-gray-500 mt-1">{{ctrans('Actual revenue per customer')}}</p>
            </div>
        </div>
    </div>
</template>
