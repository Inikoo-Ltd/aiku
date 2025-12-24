<script setup lang="ts">
import { computed, inject } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCalendarAlt, faChartLine } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'

library.add(faCalendarAlt, faChartLine)

interface IntervalData {
    period: string
    total_sales: number
    total_invoices: number
    sales_delta?: number
    sales_delta_percentage?: number
    invoices_delta?: number
    invoices_delta_percentage?: number
    previous_period_sales?: number
    previous_period_invoices?: number
}

interface SalesIntervalsData {
    currency: string
    current_year?: IntervalData
    current_quarter?: IntervalData
    year_to_date?: {
        total_sales: number
        total_invoices: number
        growth_percentage: number
    }
}

const props = defineProps<{
    intervalsData: SalesIntervalsData
}>()

const locale = inject("locale", {});

const getDeltaIndicator = (delta: number | undefined): { icon: string; color: string } => {
    if (!delta) return { icon: '─', color: 'text-gray-400' }
    
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

const formatCurrency = (amount: number) => {
    return locale.currencyFormat(props.intervalsData.currency, amount)
}

const formatNumber = (num: number) => {
    return locale.number(num)
}

const formatPercentage = (percentage: number | undefined) => {
    if (percentage === undefined) return 'N/A'
    const sign = percentage > 0 ? '+' : ''
    return `${sign}${percentage.toFixed(1)}%`
}

const getTooltip = (item: IntervalData, type: 'sales' | 'invoices') => {
    if (type === 'sales') {
        return `Sales: ${formatCurrency(item.total_sales)}\nPrevious: ${formatCurrency(item.previous_period_sales || 0)}\nChange: ${formatPercentage(item.sales_delta_percentage)}`
    } else {
        return `Invoices: ${formatNumber(item.total_invoices)}\nPrevious: ${formatNumber(item.previous_period_invoices || 0)}\nChange: ${formatPercentage(item.invoices_delta_percentage)}`
    }
}
</script>

<template>
    <div class="bg-white border border-gray-200 rounded-lg shadow-sm h-fit sticky top-4">
        <!-- Header -->
        <div class="p-4 border-b border-gray-200">
            <div class="flex items-center gap-2">
                <FontAwesomeIcon :icon="faChartLine" class="text-gray-600" />
                <h3 class="text-sm font-semibold text-gray-900">Sales Performance</h3>
            </div>
            <div class="text-xs text-gray-500 mt-1">Based on intervals data</div>
        </div>

        <!-- Current Year Performance -->
        <div v-if="intervalsData.current_year" class="p-4 border-b border-gray-200">
            <div class="flex items-center gap-2 mb-3">
                <FontAwesomeIcon :icon="faCalendarAlt" class="text-gray-500 text-xs" />
                <div class="text-xs font-semibold text-gray-700">{{ intervalsData.current_year.period }}</div>
            </div>
            
            <!-- Year Sales -->
            <div class="flex justify-between items-center mb-2">
                <span class="text-xs text-gray-600">Sales</span>
                <div class="flex items-center gap-2">
                    <span class="text-sm font-bold text-gray-900">
                        {{ formatCurrency(intervalsData.current_year.total_sales) }}
                    </span>
                    <div
                        v-if="intervalsData.current_year.sales_delta_percentage !== undefined"
                        v-tooltip="getTooltip(intervalsData.current_year, 'sales')"
                        :class="getDeltaIndicator(intervalsData.current_year.sales_delta).color"
                        class="text-xs font-bold cursor-help"
                    >
                        {{ formatPercentage(intervalsData.current_year.sales_delta_percentage) }}
                        {{ getDeltaIndicator(intervalsData.current_year.sales_delta).icon }}
                    </div>
                </div>
            </div>

            <!-- Year Invoices -->
            <div class="flex justify-between items-center">
                <span class="text-xs text-gray-600">Invoices</span>
                <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-gray-700">
                        {{ formatNumber(intervalsData.current_year.total_invoices) }}
                    </span>
                    <div
                        v-if="intervalsData.current_year.invoices_delta_percentage !== undefined"
                        v-tooltip="getTooltip(intervalsData.current_year, 'invoices')"
                        :class="getDeltaIndicator(intervalsData.current_year.invoices_delta).color"
                        class="text-xs font-bold cursor-help"
                    >
                        {{ formatPercentage(intervalsData.current_year.invoices_delta_percentage) }}
                        {{ getDeltaIndicator(intervalsData.current_year.invoices_delta).icon }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Current Quarter Performance -->
        <div v-if="intervalsData.current_quarter" class="p-4 border-b border-gray-200">
            <div class="flex items-center gap-2 mb-3">
                <FontAwesomeIcon :icon="faCalendarAlt" class="text-gray-500 text-xs" />
                <div class="text-xs font-semibold text-gray-700">{{ intervalsData.current_quarter.period }}</div>
            </div>
            
            <!-- Quarter Sales -->
            <div class="flex justify-between items-center mb-2">
                <span class="text-xs text-gray-600">Sales</span>
                <div class="flex items-center gap-2">
                    <span class="text-sm font-bold text-gray-900">
                        {{ formatCurrency(intervalsData.current_quarter.total_sales) }}
                    </span>
                    <div
                        v-if="intervalsData.current_quarter.sales_delta_percentage !== undefined"
                        v-tooltip="getTooltip(intervalsData.current_quarter, 'sales')"
                        :class="getDeltaIndicator(intervalsData.current_quarter.sales_delta).color"
                        class="text-xs font-bold cursor-help"
                    >
                        {{ formatPercentage(intervalsData.current_quarter.sales_delta_percentage) }}
                        {{ getDeltaIndicator(intervalsData.current_quarter.sales_delta).icon }}
                    </div>
                </div>
            </div>

            <!-- Quarter Invoices -->
            <div class="flex justify-between items-center">
                <span class="text-xs text-gray-600">Invoices</span>
                <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-gray-700">
                        {{ formatNumber(intervalsData.current_quarter.total_invoices) }}
                    </span>
                    <div
                        v-if="intervalsData.current_quarter.invoices_delta_percentage !== undefined"
                        v-tooltip="getTooltip(intervalsData.current_quarter, 'invoices')"
                        :class="getDeltaIndicator(intervalsData.current_quarter.invoices_delta).color"
                        class="text-xs font-bold cursor-help"
                    >
                        {{ formatPercentage(intervalsData.current_quarter.invoices_delta_percentage) }}
                        {{ getDeltaIndicator(intervalsData.current_quarter.invoices_delta).icon }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Year to Date Growth -->
        <div v-if="intervalsData.year_to_date" class="p-4 bg-gray-50">
            <div class="text-xs font-semibold text-gray-700 mb-2">Year to Date</div>
            
            <div class="flex justify-between items-center mb-1">
                <span class="text-xs text-gray-600">Total Sales</span>
                <span class="text-sm font-bold text-gray-900">
                    {{ formatCurrency(intervalsData.year_to_date.total_sales) }}
                </span>
            </div>

            <div class="flex justify-between items-center mb-2">
                <span class="text-xs text-gray-600">Total Invoices</span>
                <span class="text-sm font-semibold text-gray-700">
                    {{ formatNumber(intervalsData.year_to_date.total_invoices) }}
                </span>
            </div>

            <div class="flex justify-between items-center pt-2 border-t border-gray-200">
                <span class="text-xs text-gray-600">Growth</span>
                <span 
                    :class="[
                        'text-sm font-bold',
                        intervalsData.year_to_date.growth_percentage > 0 ? 'text-green-600' : 
                        intervalsData.year_to_date.growth_percentage < 0 ? 'text-red-600' : 
                        'text-gray-600'
                    ]"
                >
                    {{ formatPercentage(intervalsData.year_to_date.growth_percentage) }}
                </span>
            </div>
        </div>
    </div>
</template>

<style scoped>
.cursor-help {
    cursor: help;
}
</style>
