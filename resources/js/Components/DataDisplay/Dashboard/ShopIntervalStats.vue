<script setup lang="ts">
import { inject, computed } from "vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"

interface IntervalDataItem {
    raw_value?: number | string
    formatted_value?: string
}

interface IntervalData {
    visitors?: {
        all?: IntervalDataItem
    }
    sales_org_currency?: {
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

const getExpectedSales = computed(() => {
    const currentMonthSales = props.shopBlocks?.interval_data?.sales_org_currency?.mtd?.raw_value
    const lastMonthSales = props.shopBlocks?.interval_data?.sales_org_currency?.lm?.raw_value
    const currentMonthOrders = props.shopBlocks?.interval_data?.orders?.mtd?.raw_value
    const lastMonthOrders = props.shopBlocks?.interval_data?.orders?.lm?.raw_value

    if (!currentMonthSales || !lastMonthSales || !currentMonthOrders || !lastMonthOrders) {
        return locale.currencyFormat(
            props.shopBlocks?.currency_code,
            0
        )
    }

    const now = new Date()
    const currentDay = now.getDate()
    const daysInMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0).getDate()
    const remainingDays = daysInMonth - currentDay

    const currentAOV = parseFloat(String(currentMonthSales)) / Number(currentMonthOrders)
    const lastMonthAOV = parseFloat(String(lastMonthSales)) / Number(lastMonthOrders)

    const projectedAOV = (currentAOV * 0.6) + (lastMonthAOV * 0.4)

    const lastMonthDays = new Date(now.getFullYear(), now.getMonth(), 0).getDate()
    const avgOrdersPerDay = Number(lastMonthOrders) / lastMonthDays

    const currentOrderRate = Number(currentMonthOrders) / currentDay

    const projectedOrderRate = (currentOrderRate * 0.7) + (avgOrdersPerDay * 0.3)

    const projectedTotalOrders = Number(currentMonthOrders) + (projectedOrderRate * remainingDays)

    const expectedSales = projectedTotalOrders * projectedAOV

    return locale.currencyFormat(
        props.shopBlocks?.currency_code,
        expectedSales
    )
})

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

const visitorsCount = computed(() => {
    return props.shopBlocks?.interval_data?.visitors?.all?.formatted_value ?? '0'
})
</script>

<template>
    <div v-if="props.shopBlocks?.interval_data" class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4 px-4 pt-4">
        <!-- Visitors -->
        <div class="flex items-center gap-4 p-4 bg-gray-50 border shadow-sm rounded-lg">
            <div class="text-sm w-full">
                <p class="text-lg font-bold mb-1">Visitors</p>
                <span class="text-2xl font-bold">
                    {{ visitorsCount }}
                </span>
                <p class="text-xs text-gray-500 mt-1">Total visitors</p>
            </div>
        </div>

        <!-- Expected Sales -->
        <div class="flex items-center gap-4 p-4 bg-gray-50 border shadow-sm rounded-lg">
            <div class="text-sm w-full">
                <p class="text-lg font-bold mb-1">Expected Sales</p>
                <span class="text-2xl font-bold">
                    {{ getExpectedSales }}
                </span>
                <p class="text-xs text-gray-500 mt-1">Projected this month</p>
            </div>
        </div>

        <!-- Average CLV -->
        <div v-if="getAverageCLV !== null" class="flex items-center gap-4 p-4 bg-gray-50 border shadow-sm rounded-lg">
            <div class="text-sm w-full">
                <p class="text-lg font-bold mb-1">Average CLV</p>
                <span class="text-2xl font-bold">
                    {{ getAverageCLV }}
                </span>
                <p class="text-xs text-gray-500 mt-1">Customer Lifetime Value</p>
            </div>
        </div>

        <!-- Historic CLV -->
        <div v-if="getHistoricCLV !== null" class="flex items-center gap-4 p-4 bg-gray-50 border shadow-sm rounded-lg">
            <div class="text-sm w-full">
                <p class="text-lg font-bold mb-1">Historic CLV</p>
                <span class="text-2xl font-bold">
                    {{ getHistoricCLV }}
                </span>
                <p class="text-xs text-gray-500 mt-1">Actual revenue per customer</p>
            </div>
        </div>
    </div>
</template>
