<script setup lang="ts">
import { computed, inject } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faUsers } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { useFormatTime } from "@/Composables/useFormatTime";

library.add(faUsers)

interface CustomerMetrics {
    total_customers: number
    repeat_customers: number
    repeat_customers_percentage: number
}

interface YearlySales {
    year: number
    total_sales: number
    total_invoices: number
    sales_delta: number
    sales_delta_percentage: number
    previous_year_sales: number
    invoices_delta: number
    invoices_delta_percentage: number
    previous_year_invoices: number
}

interface QuarterlySales {
    quarter: string
    quarter_number: number
    year: number
    total_sales: number
    total_invoices: number
    sales_delta: number
    sales_delta_percentage: number
    previous_year_sales: number
    invoices_delta: number
    invoices_delta_percentage: number
    previous_year_invoices: number
}

interface SalesData {
    all_sales_since: string | null
    total_sales: number
    total_invoices: number
    customer_metrics: CustomerMetrics
    yearly_sales: YearlySales[]
    quarterly_sales: QuarterlySales[]
    currency: string
}

const props = defineProps<{
    salesData: SalesData
}>()

const locale = inject("locale", {});

const formattedSalesSince = computed(() => useFormatTime(props.salesData.all_sales_since))
const formattedTotalSales = computed(() => locale.currencyFormat(props.salesData.currency, props.salesData.total_sales))
const formattedTotalInvoices = computed(() => locale.number(props.salesData.total_invoices))

const customerMetrics = computed(() => ({
    total: locale.number(props.salesData.customer_metrics.total_customers),
    percentage: `${props.salesData.customer_metrics.repeat_customers_percentage.toFixed(1)}%`
}))

// Helper to get tooltip text
const getSalesTooltip = (item: YearlySales | QuarterlySales) => {
    return `Sales: ${locale.currencyFormat(props.salesData.currency, item.total_sales)}\nPrevious: ${locale.currencyFormat(props.salesData.currency, item.previous_year_sales)}\nChange: ${item.sales_delta_percentage.toFixed(1)}%`
}

const getInvoicesTooltip = (item: YearlySales | QuarterlySales) => {
    return `Invoices: ${locale.number(item.total_invoices)}\nPrevious: ${locale.number(item.previous_year_invoices)}\nChange: ${item.invoices_delta_percentage.toFixed(1)}%`
}

const getDeltaIndicator = (delta: number): { icon: string; color: string } => {
    if (delta > 0) {
        return {
            icon: '▲',
            color: 'text-green-600'
        }
    } else if (delta < 0) {
        return {
            icon: '▼',
            color: 'text-red-600'
        }
    } else {
        return {
            icon: '─',
            color: 'text-gray-400'
        }
    }
}
</script>

<template>
    <div class="bg-white border border-gray-200 rounded-lg shadow-sm h-fit sticky top-4">
        <!-- Header -->
        <div class="p-4 border-b border-gray-200">
            <h3 class="text-sm font-semibold text-gray-900">Sales Analytics</h3>
            <div class="text-xs text-gray-500 mt-1">Since {{ formattedSalesSince }}</div>
        </div>

        <!-- Summary Stats -->
        <div class="p-4 space-y-3 border-b border-gray-200">
            <!-- Total Sales -->
            <div class="flex justify-between items-center">
                <span class="text-xs text-gray-600">Total Sales</span>
                <span class="text-sm font-bold text-gray-900">{{ formattedTotalSales }}</span>
            </div>

            <!-- Total Invoices -->
            <div class="flex justify-between items-center">
                <span class="text-xs text-gray-600">Invoices</span>
                <span class="text-sm font-bold text-gray-900">{{ formattedTotalInvoices }}</span>
            </div>

            <!-- Customers -->
            <div class="flex justify-between items-center">
                <span class="text-xs text-gray-600 flex justify-center items-center gap-1">
                    <FontAwesomeIcon :icon="faUsers" class="text-gray-500" />
                    Customers
                </span>
                <span class="text-sm font-bold text-gray-900">{{ customerMetrics.total }}</span>
            </div>

            <!-- Repeat Rate -->
            <div class="flex justify-between items-center">
                <span class="text-xs text-gray-600">Repeat Rate</span>
                <span class="text-sm font-semibold text-blue-600">{{ customerMetrics.percentage }}</span>
            </div>
        </div>

        <!-- Yearly Performance -->
        <div class="p-4 border-b border-gray-200">
            <div class="text-xs font-semibold text-gray-700 mb-3">Yearly Performance</div>
            <div class="overflow-x-auto">
                <!-- Headers -->
                <div class="grid grid-cols-5 gap-1 mb-2 min-w-max">
                    <div
                        v-for="year in salesData.yearly_sales"
                        :key="year.year"
                        class="text-center text-xs font-semibold text-gray-900 p-1"
                    >
                        {{ year.year }}
                    </div>
                </div>

                <!-- Sales Row -->
                <div class="grid grid-cols-5 gap-1 mb-2 min-w-max">
                    <div
                        v-for="year in salesData.yearly_sales"
                        :key="`sales-${year.year}`"
                        class="flex justify-center items-center gap-1 text-center p-1"
                    >
                        <div class="text-xs font-semibold text-gray-900">
                            {{ locale.currencyFormat(salesData.currency, year.total_sales) }}
                        </div>
                        <div
                            v-tooltip="getSalesTooltip(year)"
                            :class="getDeltaIndicator(year.sales_delta).color"
                            class="text-sm font-bold cursor-help"
                        >
                            {{ getDeltaIndicator(year.sales_delta).icon }}
                        </div>
                    </div>
                </div>

                <!-- Invoices Row -->
                <div class="grid grid-cols-5 gap-1 min-w-max">
                    <div
                        v-for="year in salesData.yearly_sales"
                        :key="`inv-${year.year}`"
                        class="flex justify-center items-center gap-1 text-center p-1"
                    >
                        <div class="text-xs font-semibold text-gray-700">
                            {{ locale.number(year.total_invoices) }}
                        </div>
                        <div
                            v-tooltip="getInvoicesTooltip(year)"
                            :class="getDeltaIndicator(year.invoices_delta).color"
                            class="text-sm font-bold cursor-help"
                        >
                            {{ getDeltaIndicator(year.invoices_delta).icon }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quarterly Performance -->
        <div class="p-4">
            <div class="text-xs font-semibold text-gray-700 mb-3">Quarterly Performance</div>
            <div class="overflow-x-auto">
                <!-- Headers -->
                <div class="grid grid-cols-5 gap-1 mb-2 min-w-max">
                    <div
                        v-for="quarter in salesData.quarterly_sales"
                        :key="quarter.quarter"
                        class="text-center text-xs font-semibold text-gray-900 p-1"
                    >
                        {{ quarter.quarter }}
                    </div>
                </div>

                <!-- Sales Row -->
                <div class="grid grid-cols-5 gap-1 mb-2 min-w-max">
                    <div
                        v-for="quarter in salesData.quarterly_sales"
                        :key="`sales-${quarter.quarter}`"
                        class="flex justify-center items-center gap-1 text-center p-1"
                    >
                        <div class="text-xs font-semibold text-gray-900">
                            {{ locale.currencyFormat(salesData.currency, quarter.total_sales) }}
                        </div>
                        <div
                            v-tooltip="getSalesTooltip(quarter)"
                            :class="getDeltaIndicator(quarter.sales_delta).color"
                            class="text-sm font-bold cursor-help"
                        >
                            {{ getDeltaIndicator(quarter.sales_delta).icon }}
                        </div>
                    </div>
                </div>

                <!-- Invoices Row -->
                <div class="grid grid-cols-5 gap-1 min-w-max">
                    <div
                        v-for="quarter in salesData.quarterly_sales"
                        :key="`inv-${quarter.quarter}`"
                        class="flex justify-center items-center gap-1 text-center p-1"
                    >
                        <div class="text-xs font-semibold text-gray-700">
                            {{ locale.number(quarter.total_invoices) }}
                        </div>
                        <div
                            v-tooltip="getInvoicesTooltip(quarter)"
                            :class="getDeltaIndicator(quarter.invoices_delta).color"
                            class="text-sm font-bold cursor-help"
                        >
                            {{ getDeltaIndicator(quarter.invoices_delta).icon }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.cursor-help {
    cursor: help;
}
</style>
