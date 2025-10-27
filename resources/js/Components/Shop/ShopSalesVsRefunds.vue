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
    interval: string
    data: any
}>()

const locale = inject("locale", aikuLocaleStructure)

const refundRatio = computed(() => {
    const revenue = Number(props.data.sales_org_currency?.[props.interval].raw_value)
    const refunds = Number(props.data.lost_revenue_other_amount?.[props.interval].raw_value)

    return revenue > 0 ? (refunds / revenue) * 100 : 0
})

const invoicesRefundRatio = computed(() => {
    const number_invoices = Number(props.data.invoices?.[props.interval].raw_value)
    const number_invoices_refund = Number(props.data.refunds?.[props.interval].raw_value)

    return number_invoices > 0
        ? (number_invoices_refund / number_invoices) * 100
        : 0
})
</script>

<template>
    <div
        :class="[
            'flex items-center gap-4 p-4 bg-gray-50 border shadow-sm rounded-lg',
            {
                hidden: (props.data.sales_org_currency?.[props.interval].raw_value || 0) <= 0
            }
        ]"
    >
        <div class="text-sm text-gray-700 w-full">
            <div class="mb-1 text-gray-400">
                {{ trans('Revenue') }}
            </div>

            <p>
                <span class="font-semibold">{{ trans("Total Sales") }}:</span>
                {{
                    props.data.sales_org_currency?.[props.interval].formatted_value || 0
                }}
                <span class="text-xs text-gray-500">
                    ({{ refundRatio.toFixed(2) }}% {{ trans("refunded") }})
                </span>
            </p>

            <p>
                <span class="font-semibold">{{ trans("Invoices") }}:</span>
                {{
                    props.data.invoices?.[props.interval].formatted_value || 0
                }}
                <span class="text-xs text-gray-500">
                    ({{ invoicesRefundRatio.toFixed(1) }}% {{ trans("with refunds") }})
                </span>
            </p>
        </div>
    </div>
</template>
