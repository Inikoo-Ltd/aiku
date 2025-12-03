<script setup lang="ts">
import { inject, computed } from "vue"
import { trans } from "laravel-vue-i18n"
import { Pie } from "vue-chartjs"
import {
    Chart as ChartJS,
    Title,
    Tooltip,
    Legend,
    ArcElement
} from "chart.js"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"

ChartJS.register(Title, Tooltip, Legend, ArcElement)

const props = defineProps<{
    data: {
        revenue_amount: number
        lost_revenue_other_amount: number
        number_invoices: number
        number_invoices_type_refund: number
    }
    currencyCode: { code: string }
}>()

const locale = inject('locale', aikuLocaleStructure)

const refundRatio = computed(() => {
    const { revenue_amount, lost_revenue_other_amount } = props.data
    if (!revenue_amount) return 0
    return (lost_revenue_other_amount / revenue_amount) * 100
})

const invoicesRefundRatio = computed(() => {
    const { number_invoices, number_invoices_type_refund } = props.data
    if (!number_invoices) return 0
    return (number_invoices_type_refund / number_invoices) * 100
})

const chartColors = ["#0D9488", "#F97316"]

const chartData = computed(() => ({
    labels: [trans("Total Sales"), trans("Refunds")],
    datasets: [
        {
            backgroundColor: chartColors,
            data: [
                props.data.revenue_amount || 0,
                props.data.lost_revenue_other_amount || 0
            ],
            borderWidth: 2,
            hoverOffset: 8
        }
    ]
}))

const chartOptions = {
    responsive: true,
    plugins: {
        legend: { display: false },
        tooltip: {
            callbacks: {
                label: (context: any) =>
                    `${locale.currencyFormat(context.code, context.parsed || 0)}`
            }
        },
    }
}
</script>

<template>
    <div :class="['flex items-center gap-4 p-4 h-32 bg-white border shadow-sm rounded-xl', { hidden: data?.revenue_amount <= 0 }]">
        <div class="text-xs text-gray-700 w-full">
            <div class="flex items-center gap-3 mb-2">
                <div class="flex items-center gap-1">
                    <span class="w-3 h-3 rounded-sm" :style="{ backgroundColor: chartColors[0] }"></span>
                    <span>{{ trans('Total Sales') }}</span>
                </div>
                <div class="flex items-center gap-1">
                    <span class="w-3 h-3 rounded-sm" :style="{ backgroundColor: chartColors[1] }"></span>
                    <span>{{ trans('Refunds') }}</span>
                </div>
            </div>

            <p>
                <span class="font-semibold">{{ trans("Total Sales") }}: </span>
                {{ locale.currencyFormat(currencyCode?.code, data?.revenue_amount || 0) }}
                <span class="text-[10px] text-gray-500">
                    ({{ refundRatio.toFixed(2) }}% {{ trans("refunded") }})
                </span>
            </p>
            <p>
                <span class="font-semibold">{{ trans("Invoices") }}: </span>
                {{ data?.number_invoices?.toLocaleString() }}
                <span class="text-[10px] text-gray-500">
                    ({{ invoicesRefundRatio.toFixed(1) }}% {{ trans("with refunds") }})
                </span>
            </p>
        </div>

        <div class="w-24 h-24">
            <Pie :data="chartData" :options="chartOptions" />
        </div>
    </div>
</template>
