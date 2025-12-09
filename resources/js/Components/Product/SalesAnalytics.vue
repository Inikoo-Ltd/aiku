<script setup lang="ts">
import { computed } from 'vue'
import { useNumberFormat } from '@/Composables/useNumberFormat'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faUsers } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'

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

const { formatCurrency, formatNumber, formatDate, getDeltaIndicator, formatPercentage } = useNumberFormat()

// Computed formatted values
const formattedSalesSince = computed(() => formatDate(props.salesData.all_sales_since))
const formattedTotalSales = computed(() => formatCurrency(props.salesData.total_sales, props.salesData.currency))
const formattedTotalInvoices = computed(() => formatNumber(props.salesData.total_invoices))

const customerMetrics = computed(() => ({
    total: formatNumber(props.salesData.customer_metrics.total_customers),
    repeat: formatNumber(props.salesData.customer_metrics.repeat_customers),
    percentage: formatPercentage(props.salesData.customer_metrics.repeat_customers_percentage, 1)
}))

// Helper to get tooltip text
const getTooltip = (current: number, previous: number, delta: number, deltaPercentage: number, currency?: string) => {
    const formatter = currency ? (v: number) => formatCurrency(v, currency) : formatNumber
    return `Current: ${formatter(current)}\nPrevious: ${formatter(previous)}\nChange: ${formatter(delta)} (${formatPercentage(deltaPercentage)})`
}
</script>

<template>
    <div class="p-6 bg-gray-50 min-h-screen">
        <!-- Header -->
        <div class="mb-6">
            <div class="text-sm text-gray-600">
                All sales since: <span class="font-medium text-gray-900">{{ formattedSalesSince }}</span>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
            <!-- Total Sales Card -->
            <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
                <div class="text-sm text-gray-600 mb-2">Total Sales</div>
                <div class="text-3xl font-bold text-gray-900">{{ formattedTotalSales }}</div>
            </div>

            <!-- Total Invoices Card -->
            <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
                <div class="text-sm text-gray-600 mb-2">Total Invoices</div>
                <div class="text-3xl font-bold text-gray-900">{{ formattedTotalInvoices }}</div>
            </div>

            <!-- Customer Metrics Card -->
            <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
                <div class="text-sm text-gray-600 mb-2 flex items-center gap-2">
                    <FontAwesomeIcon :icon="faUsers" class="text-gray-500" />
                    Customers
                </div>
                <div class="text-3xl font-bold text-gray-900">{{ customerMetrics.total }}</div>
                <div class="text-sm text-gray-600 mt-2">
                    <span class="font-medium text-blue-600">{{ customerMetrics.repeat }}</span>
                    repeat ({{ customerMetrics.percentage }})
                </div>
            </div>
        </div>

        <!-- Yearly Breakdown -->
        <div class="mb-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Yearly Performance</h3>
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-x-auto">
                <div class="min-w-max">
                    <!-- Headers -->
                    <div class="grid grid-cols-5 border-b border-gray-200">
                        <div
                            v-for="year in salesData.yearly_sales"
                            :key="year.year"
                            class="p-4 text-center font-semibold text-gray-900 border-r border-gray-200 last:border-r-0"
                        >
                            {{ year.year }}
                        </div>
                    </div>

                    <!-- Sales Row -->
                    <div class="grid grid-cols-5 border-b border-gray-200">
                        <div
                            v-for="year in salesData.yearly_sales"
                            :key="`sales-${year.year}`"
                            class="p-4 text-center border-r border-gray-200 last:border-r-0"
                        >
                            <div class="font-semibold text-gray-900">
                                {{ formatCurrency(year.total_sales, salesData.currency) }}
                            </div>
                            <div
                                v-tooltip="getTooltip(year.total_sales, year.previous_year_sales, year.sales_delta, year.sales_delta_percentage, salesData.currency)"
                                :class="getDeltaIndicator(year.sales_delta).color"
                                class="text-xl font-bold mt-1 cursor-help"
                            >
                                {{ getDeltaIndicator(year.sales_delta).icon }}
                            </div>
                            <div class="text-xs text-gray-500 mt-1">
                                {{ formatPercentage(year.sales_delta_percentage) }}
                            </div>
                        </div>
                    </div>

                    <!-- Invoices Row -->
                    <div class="grid grid-cols-5">
                        <div
                            v-for="year in salesData.yearly_sales"
                            :key="`invoices-${year.year}`"
                            class="p-4 text-center border-r border-gray-200 last:border-r-0"
                        >
                            <div class="font-semibold text-gray-700">
                                {{ formatNumber(year.total_invoices) }}
                            </div>
                            <div
                                v-tooltip="getTooltip(year.total_invoices, year.previous_year_invoices, year.invoices_delta, year.invoices_delta_percentage)"
                                :class="getDeltaIndicator(year.invoices_delta).color"
                                class="text-xl font-bold mt-1 cursor-help"
                            >
                                {{ getDeltaIndicator(year.invoices_delta).icon }}
                            </div>
                            <div class="text-xs text-gray-500 mt-1">
                                {{ formatPercentage(year.invoices_delta_percentage) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quarterly Breakdown -->
        <div>
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Quarterly Performance</h3>
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-x-auto">
                <div class="min-w-max">
                    <!-- Headers -->
                    <div class="grid grid-cols-5 border-b border-gray-200">
                        <div
                            v-for="quarter in salesData.quarterly_sales"
                            :key="quarter.quarter"
                            class="p-4 text-center font-semibold text-gray-900 border-r border-gray-200 last:border-r-0"
                        >
                            {{ quarter.quarter }}
                        </div>
                    </div>

                    <!-- Sales Row -->
                    <div class="grid grid-cols-5 border-b border-gray-200">
                        <div
                            v-for="quarter in salesData.quarterly_sales"
                            :key="`sales-${quarter.quarter}`"
                            class="p-4 text-center border-r border-gray-200 last:border-r-0"
                        >
                            <div class="font-semibold text-gray-900">
                                {{ formatCurrency(quarter.total_sales, salesData.currency) }}
                            </div>
                            <div
                                v-tooltip="getTooltip(quarter.total_sales, quarter.previous_year_sales, quarter.sales_delta, quarter.sales_delta_percentage, salesData.currency)"
                                :class="getDeltaIndicator(quarter.sales_delta).color"
                                class="text-xl font-bold mt-1 cursor-help"
                            >
                                {{ getDeltaIndicator(quarter.sales_delta).icon }}
                            </div>
                            <div class="text-xs text-gray-500 mt-1">
                                {{ formatPercentage(quarter.sales_delta_percentage) }}
                            </div>
                        </div>
                    </div>

                    <!-- Invoices Row -->
                    <div class="grid grid-cols-5">
                        <div
                            v-for="quarter in salesData.quarterly_sales"
                            :key="`invoices-${quarter.quarter}`"
                            class="p-4 text-center border-r border-gray-200 last:border-r-0"
                        >
                            <div class="font-semibold text-gray-700">
                                {{ formatNumber(quarter.total_invoices) }}
                            </div>
                            <div
                                v-tooltip="getTooltip(quarter.total_invoices, quarter.previous_year_invoices, quarter.invoices_delta, quarter.invoices_delta_percentage)"
                                :class="getDeltaIndicator(quarter.invoices_delta).color"
                                class="text-xl font-bold mt-1 cursor-help"
                            >
                                {{ getDeltaIndicator(quarter.invoices_delta).icon }}
                            </div>
                            <div class="text-xs text-gray-500 mt-1">
                                {{ formatPercentage(quarter.invoices_delta_percentage) }}
                            </div>
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
