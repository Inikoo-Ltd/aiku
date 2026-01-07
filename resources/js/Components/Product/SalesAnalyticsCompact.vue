<script setup lang="ts">
import { computed, inject } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faUsers, faEquals } from '@fal'
import { faTriangle } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
import { useFormatTime } from "@/Composables/useFormatTime";
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure';

library.add(faUsers, faTriangle, faEquals)

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
    total_customers: number
    yearly_sales: YearlySales[]
    quarterly_sales: QuarterlySales[]
    currency: string
}

const props = defineProps<{
    salesData: SalesData
}>()

const locale = inject("locale", aikuLocaleStructure);

// Helper: Generate 5 latest years (descending)
function getLastNYears(n: number, fromYear?: number): number[] {
    const currentYear = fromYear ?? new Date().getFullYear();
    return Array.from({ length: n }, (_, i) => currentYear - i);
}

// Helper: Generate 5 latest quarters (descending) for each year
function getLastNQuarters(n: number, fromYear?: number): { year: number, quarter: number }[] {
    const now = new Date();
    let year = fromYear ?? now.getFullYear();
    let quarter = Math.floor((now.getMonth()) / 3) + 1;
    const quarters: { year: number, quarter: number }[] = [];
    for (let i = 0; i < n; i++) {
        quarters.push({ year, quarter });
        quarter--;
        if (quarter === 0) {
            quarter = 4;
            year--;
        }
    }
    return quarters;
}

// Compose 5 years data for grid (descending, fill 0 if not exist)
const yearlySalesGrid = computed<YearlySales[]>(() => {
    const backend = props.salesData.yearly_sales || [];
    // Find the latest year from backend, fallback to current year
    const latestYear = backend.length > 0 ? Math.max(...backend.map(y => y.year)) : new Date().getFullYear();
    const years = getLastNYears(5, latestYear);
    return years.map(year => {
        const found = backend.find(y => y.year === year);
        return found ?? {
            year,
            total_sales: 0,
            total_invoices: 0,
            sales_delta: 0,
            sales_delta_percentage: 0,
            previous_year_sales: 0,
            invoices_delta: 0,
            invoices_delta_percentage: 0,
            previous_year_invoices: 0,
        };
    });
});

// Compose 5 quarters data for grid (descending, fill 0 if not exist)
const quarterlySalesGrid = computed<QuarterlySales[]>(() => {
    const backend = props.salesData.quarterly_sales || [];
    // Find the latest year/quarter from backend, fallback to current
    let latestYear = new Date().getFullYear();
    let latestQuarter = Math.floor((new Date().getMonth()) / 3) + 1;
    if (backend.length > 0) {
        // Find the latest by year, then quarter
        const sorted = [...backend].sort((a, b) => (b.year - a.year) || (b.quarter_number - a.quarter_number));
        latestYear = sorted[0].year;
        latestQuarter = sorted[0].quarter_number;
    }
    const quarters = getLastNQuarters(5, latestYear);
    return quarters.map(({ year, quarter }) => {
        const found = backend.find(q => q.year === year && q.quarter_number === quarter);
        // Compose quarter string as in backend: Q{quarter} {year.toString().slice(-2)}
        const quarterStr = `Q${quarter} ${year.toString().slice(-2)}`;
        return found ?? {
            quarter: quarterStr,
            quarter_number: quarter,
            year,
            total_sales: 0,
            total_invoices: 0,
            sales_delta: 0,
            sales_delta_percentage: 0,
            previous_year_sales: 0,
            invoices_delta: 0,
            invoices_delta_percentage: 0,
            previous_year_invoices: 0,
        };
    });
});

const formattedSalesSince = computed(() => useFormatTime(props.salesData.all_sales_since))
const formattedTotalSales = computed(() => locale.currencyFormat(props.salesData.currency, props.salesData.total_sales))
const formattedTotalInvoices = computed(() => locale.number(props.salesData.total_invoices))
const formattedTotalCustomers = computed(() => locale.number(props.salesData.total_customers))

// Helper to get tooltip text
const getSalesTooltip = (item: YearlySales | QuarterlySales) => {
    return `Sales: ${locale.currencyFormat(props.salesData.currency, item.total_sales)}\nPrevious: ${locale.currencyFormat(props.salesData.currency, item.previous_year_sales)}\nChange: ${item.sales_delta_percentage.toFixed(1)}%`
}

const getInvoicesTooltip = (item: YearlySales | QuarterlySales) => {
    return `Invoices: ${locale.number(item.total_invoices)}\nPrevious: ${locale.number(item.previous_year_invoices)}\nChange: ${item.invoices_delta_percentage.toFixed(1)}%`
}

const getDeltaIndicator = (delta: number) => {
    if (delta > 0) {
        return {
            icon: faTriangle,
            color: 'text-green-600',
            class: ''
        }
    } else if (delta < 0) {
        return {
            icon: faTriangle,
            color: 'text-red-600',
            class: 'rotate-180'
        }
    } else {
        return {
            icon: faEquals,
            color: 'text-gray-400',
            class: ''
        }
    }
}
</script>

<template>
    <div class="bg-white border border-gray-200 rounded-lg shadow-xs w-full h-fit">
        <!-- Header -->
        <div class="p-4 border-b border-gray-200">
            <h3 class="text-sm font-semibold text-gray-900">Sales Analytics</h3>
            <div class="text-xs text-gray-500 mt-1">Since {{ formattedSalesSince }}</div>
        </div>

        <!-- Summary Stats as 3-column grid, align center, with tooltip for each column -->
        <div class="p-4 border-b border-gray-200">
            <div class="grid grid-cols-3 gap-0 text-center">
                <!-- Total Sales -->
                <div class="flex flex-col items-center justify-center cursor-pointer" v-tooltip="'Total Sales'">
                    <span class="text-sm font-bold text-gray-900 w-full">{{ formattedTotalSales }}</span>
                </div>
                <!-- Total Invoices -->
                <div class="flex flex-col items-center justify-center cursor-pointer" v-tooltip="'Total Invoices'">
                    <span class="text-sm font-bold text-gray-900 w-full">{{ formattedTotalInvoices }}</span>
                </div>
                <!-- Customers with icon -->
                <div class="flex flex-col items-center justify-center cursor-pointer" v-tooltip="'Customers'">
                    <span>
                        <FontAwesomeIcon :icon="faUsers" class="text-gray-500 mr-1" />
                        <span class="text-sm font-bold text-gray-900">{{ formattedTotalCustomers }}</span>
                    </span>
                </div>
            </div>
        </div>

        <!-- Yearly Performance -->
        <div class="p-4 border-b border-gray-200">
            <div class="text-xs font-semibold text-gray-700 mb-3">Yearly Performance</div>
            <div class="overflow-x-auto">
                <!-- Headers -->
                <div class="grid grid-cols-5 min-w-max divide-x divide-gray-200 border border-gray-200 rounded-t">
                    <div
                        v-for="year in yearlySalesGrid"
                        :key="year.year"
                        class="text-right text-xs font-semibold text-gray-900 p-1 bg-gray-50"
                    >
                        {{ year.year }}
                    </div>
                </div>

                <!-- Sales Row -->
                <div class="grid grid-cols-5 min-w-max divide-x divide-gray-200 border-l border-r border-gray-200">
                    <div
                        v-for="year in yearlySalesGrid"
                        :key="`sales-${year.year}`"
                        v-tooltip="getSalesTooltip(year)"
                        class="flex justify-end items-center gap-1 text-right p-1 cursor-pointer bg-white"
                    >
                        <div class="text-xs font-semibold text-gray-900">
                            {{ locale.CurrencyShort(props.salesData.currency, year.total_sales) }}
                        </div>
                        <div
                            :class="getDeltaIndicator(year.sales_delta).color"
                            class="text-sm font-bold"
                        >
                            <FontAwesomeIcon size="sm" :icon="getDeltaIndicator(year.sales_delta).icon" :class="getDeltaIndicator(year.sales_delta).class" />
                        </div>
                    </div>
                </div>

                <!-- Invoices Row -->
                <div class="grid grid-cols-5 min-w-max divide-x divide-gray-200 border border-gray-200 rounded-b">
                    <div
                        v-for="year in yearlySalesGrid"
                        :key="`inv-${year.year}`"
                        v-tooltip="getInvoicesTooltip(year)"
                        class="flex justify-end items-center gap-1 text-right p-1 cursor-pointer bg-white"
                    >
                        <div class="text-xs font-semibold text-gray-700">
                            {{ locale.numberShort(year.total_invoices) }}
                        </div>
                        <div
                            :class="getDeltaIndicator(year.invoices_delta).color"
                            class="text-sm font-bold"
                        >
                            <FontAwesomeIcon size="sm" :icon="getDeltaIndicator(year.invoices_delta).icon" :class="getDeltaIndicator(year.invoices_delta).class" />
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
                <div class="grid grid-cols-5 min-w-max divide-x divide-gray-200 border border-gray-200 rounded-t">
                    <div
                        v-for="quarter in quarterlySalesGrid"
                        :key="quarter.quarter"
                        class="text-right text-xs font-semibold text-gray-900 p-1 bg-gray-50"
                    >
                        {{ quarter.quarter }}
                    </div>
                </div>

                <!-- Sales Row -->
                <div class="grid grid-cols-5 min-w-max divide-x divide-gray-200 border-l border-r border-gray-200">
                    <div
                        v-for="quarter in quarterlySalesGrid"
                        :key="`sales-${quarter.quarter}`"
                        v-tooltip="getSalesTooltip(quarter)"
                        class="flex justify-end items-center gap-1 text-right p-1 cursor-pointer bg-white"
                    >
                        <div class="text-xs font-semibold text-gray-900">
                            {{ locale.CurrencyShort(props.salesData.currency, quarter.total_sales) }}
                        </div>
                        <div
                            :class="getDeltaIndicator(quarter.sales_delta).color"
                            class="text-sm font-bold"
                        >
                            <FontAwesomeIcon size="sm" :icon="getDeltaIndicator(quarter.sales_delta).icon" :class="getDeltaIndicator(quarter.sales_delta).class" />
                        </div>
                    </div>
                </div>

                <!-- Invoices Row -->
                <div class="grid grid-cols-5 min-w-max divide-x divide-gray-200 border border-gray-200 rounded-b">
                    <div
                        v-for="quarter in quarterlySalesGrid"
                        :key="`inv-${quarter.quarter}`"
                        v-tooltip="getInvoicesTooltip(quarter)"
                        class="flex justify-end items-center gap-1 text-right p-1 cursor-pointer bg-white"
                    >
                        <div class="text-xs font-semibold text-gray-700">
                            {{ locale.numberShort(quarter.total_invoices) }}
                        </div>
                        <div
                            :class="getDeltaIndicator(quarter.invoices_delta).color"
                            class="text-sm font-bold"
                        >
                            <FontAwesomeIcon size="sm" :icon="getDeltaIndicator(quarter.invoices_delta).icon" :class="getDeltaIndicator(quarter.invoices_delta).class" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
