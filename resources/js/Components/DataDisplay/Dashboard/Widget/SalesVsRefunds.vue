<script setup lang="ts">
import { inject, computed, defineProps } from "vue"
import { trans } from "laravel-vue-i18n"
import {
    Chart as ChartJS,
    Title,
    Tooltip,
    Legend,
    ArcElement
} from "chart.js"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { faInfoCircle } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

ChartJS.register(Title, Tooltip, Legend, ArcElement)

const props = defineProps<{
    scope: "group" | "organisation"
    intervals: {
        options: {
            label: string
            value: string
            labelShort: string
        }[]
        value: string
    }
    tableData: Record<string, any>
}>()

const locale = inject("locale", aikuLocaleStructure)

const keyForScope = (base: string) => {
    return props.scope === "group"
        ? base.replace("_org_", "_grp_")
        : base
}

const refundRatio = computed(() => {
    const salesKey = keyForScope("sales_org_currency")
    const refundKey = keyForScope("lost_revenue_other_amount_org_currency")

    const revenue = Number(
        props.tableData?.tables?.invoice_categories?.totals?.columns?.[salesKey]?.[props.intervals.value]?.raw_value || 0
    )
    const refunds = Number(
        props.tableData?.tables?.invoice_categories?.totals?.columns?.[refundKey]?.[props.intervals.value]?.raw_value || 0
    )

    return revenue > 0 ? (refunds / revenue) * 100 : 0
})

const invoicesRefundRatio = computed(() => {
    const number_invoices = Number(
        props.tableData?.tables?.invoice_categories?.totals?.columns
            ?.invoices?.[props.intervals.value]?.raw_value || 0
    )
    const number_invoices_refund = Number(
        props.tableData?.tables?.invoice_categories?.totals?.columns
            ?.refunds?.[props.intervals.value]?.raw_value || 0
    )

    return number_invoices > 0
        ? (number_invoices_refund / number_invoices) * 100
        : 0
})

const chartColors = ["#0D9488", "#F97316"]

const chartData = computed(() => {
    const salesKey = keyForScope("sales_org_currency")
    const refundKey = keyForScope("lost_revenue_other_amount_org_currency")

    const revenue = Number(
        props.tableData?.tables?.invoice_categories?.totals?.columns?.[salesKey]?.[props.intervals.value]?.raw_value || 0
    )
    const refunds = Number(
        props.tableData?.tables?.invoice_categories?.totals?.columns?.[refundKey]?.[props.intervals.value]?.raw_value || 0
    )

    return {
        labels: [trans("Total Sales"), trans("Refunds")],
        datasets: [
            {
                backgroundColor: chartColors,
                data: [revenue, refunds],
                borderWidth: 2,
                hoverOffset: 8
            }
        ]
    }
})
</script>

<template>
    <div
        :class="[
            'flex items-center gap-4 p-4 bg-gray-50 border shadow-sm rounded-lg',
            {
                hidden:
                    (props.tableData?.tables?.invoice_categories?.totals?.columns?.[
                        props.scope === 'group'
                            ? 'sales_grp_currency'
                            : 'sales_org_currency'
                    ]?.[props.intervals.value]?.raw_value || 0) <= 0
            }
        ]"
    >
        <div class="text-sm text-gray-700 w-full">
            <div class="text-base mb-1 text-gray-400">
                {{ trans('Revenue') }}
                <FontAwesomeIcon
                    v-tooltip="trans('Shows the breakdown of total sales and refunds')"
                    :icon="faInfoCircle"
                    class="hover:text-gray-600"
                    fixed-width
                    aria-hidden="true"
                />
            </div>

            <p>
                <span class="font-semibold">{{ trans("Total Sales") }}:</span>
                {{
                    props.tableData?.tables?.invoice_categories?.totals?.columns?.[
                        props.scope === 'group'
                            ? 'sales_grp_currency'
                            : 'sales_org_currency'
                        ]?.[props.intervals.value]?.formatted_value || 0
                }}
                <span class="text-xs text-gray-500">
                    ({{ refundRatio.toFixed(2) }}% {{ trans("refunded") }})
                </span>
            </p>

            <p>
                <span class="font-semibold">{{ trans("Invoices") }}:</span>
                {{
                    props.tableData?.tables?.invoice_categories?.totals?.columns
                        ?.invoices?.[props.intervals.value]?.formatted_value || 0
                }}
                <span class="text-xs text-gray-500">
                    ({{ invoicesRefundRatio.toFixed(1) }}% {{ trans("with refunds") }})
                </span>
            </p>
        </div>
    </div>
</template>
