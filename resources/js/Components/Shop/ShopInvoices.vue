<script setup lang="ts">
import { inject, computed, defineProps } from "vue"
import { trans } from "laravel-vue-i18n"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"

const props = defineProps<{
    interval: string
    data: any
}>()

const locale = inject("locale", aikuLocaleStructure)

const invoicesRefundRatio = computed(() => {
    const number_invoices = Number(props.data.invoices?.[props.interval].raw_value)
    const number_invoices_refund = Number(props.data.refunds?.[props.interval].raw_value)

    return number_invoices > 0
        ? (number_invoices_refund / number_invoices) * 100
        : 0
})
</script>

<template>
    <div :class="['flex items-center gap-4 p-4 bg-gray-50 border shadow-sm rounded-lg', { hidden: (props.data.sales_org_currency?.[props.interval].raw_value || 0) <= 0 }]">
        <div class="text-sm w-full">
            <p class="text-lg font-bold mb-1">{{ trans('Invoices') }}</p>
            <p class="flex flex-col">
                <span class="text-2xl font-bold">{{ props.data.invoices?.[props.interval].formatted_value || 0 }}</span>
                <span>
                    ({{ invoicesRefundRatio.toFixed(1) }}%
                    <span class="italic">{{ trans("with refunds") }})</span>
                </span>
            </p>
        </div>
    </div>
</template>
