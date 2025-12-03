<script setup lang="ts">
import { inject, computed } from "vue"
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


const getYoYComparison = (metric: string) => {
    const delta = props.data[`${metric}_delta`]?.[props.interval];
    if (!delta || delta.raw_value === 9999999) return null;

    return {
        value: delta.formatted_value,
        isPositive: delta.raw_value > 1,
        isNegative: delta.raw_value < 1
    };
}
</script>

<template>
    <div :class="['flex items-center gap-4 h-32 p-4 bg-gray-50 border shadow-sm rounded-lg', { hidden: (props.data.sales_org_currency?.[props.interval].raw_value || 0) <= 0 }]">
        <div class="text-sm w-full">
            <p class="text-lg font-bold mb-1">{{ trans('Invoices') }}</p>
            <p class="flex flex-col">
                <span class="text-2xl font-bold">
                    {{ props.data.invoices?.[props.interval].formatted_value || 0 }}
                    <span v-if="getYoYComparison('invoices')" :class="['italic text-base font-medium ml-1', { 'text-green-500': getYoYComparison('invoices')?.isPositive, 'text-red-500': getYoYComparison('invoices')?.isNegative }]">
                        {{ getYoYComparison('invoices')?.value }}
                    </span>
                </span>
                <span>
                    ({{ invoicesRefundRatio.toFixed(1) }}%
                    <span class="italic">{{ trans("with refunds") }})</span>
                </span>
            </p>
        </div>
    </div>
</template>
