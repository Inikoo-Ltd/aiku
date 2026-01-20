<script setup lang="ts">
import { inject, computed } from "vue"
import { trans } from "laravel-vue-i18n"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"

const props = defineProps<{
    interval: string
    data: any
}>()

const locale = inject("locale", aikuLocaleStructure)

const refundRatio = computed(() => {
    const revenue = Number(props.data.sales_org_currency?.[props.interval]?.raw_value || 0)
    const refunds = Number(props.data.lost_revenue_other_amount?.[props.interval]?.raw_value || 0)

    if (revenue > 0) {
        const ratio = (Math.abs(refunds) / revenue) * 100
        return isNaN(ratio) ? 0 : ratio
    }

    return 0
})

const getYoYComparison = (metric: string) => {
    if (props.interval === 'all') {
        return null;
    }
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
    <div :class="['flex items-center gap-4 p-4 min-h-32 bg-gray-50 border shadow-sm rounded-lg transform transition-transform hover:scale-105', { hidden: (props.data.sales_org_currency?.[props.interval].raw_value || 0) <= 0 }]">
        <div class="text-sm w-full">
            <p class="text-lg font-bold mb-1">{{ trans('Sales') }}</p>
            <p class="flex flex-col">
                <span class="text-2xl font-bold">
                    {{ props.data.sales_org_currency?.[props.interval].formatted_value || 0 }}
                    <span v-if="getYoYComparison('sales_org_currency')" :class="['italic text-base font-medium ml-1', { 'text-green-500': getYoYComparison('sales_org_currency')?.isPositive, 'text-red-500': getYoYComparison('sales_org_currency')?.isNegative }]">
                        {{ getYoYComparison('sales_org_currency')?.value }}
                    </span>
                </span>
                <span>
                    ({{ refundRatio.toFixed(1) }}%
                    <span class="italic">{{ trans("with refunds") }})</span>
                </span>
            </p>
        </div>
    </div>
</template>
