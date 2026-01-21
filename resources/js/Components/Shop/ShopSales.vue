<script setup lang="ts">
import { computed } from "vue"
import { trans } from "laravel-vue-i18n"

const props = defineProps<{
    interval: string
    data: any
}>()

const refundRatio = computed(() => {
    const refunds = Math.abs(Number(props.data.lost_revenue?.[props.interval]?.raw_value || 0))
    const revenue = Number(props.data.sales?.[props.interval]?.raw_value || 0) + refunds

    if (revenue > 0) {
        const ratio = (refunds / revenue) * 100
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
    <div :class="['flex items-center gap-4 p-4 min-h-32 bg-gray-50 border shadow-sm rounded-lg transform transition-transform hover:scale-105', { hidden: (props.data.sales?.[props.interval].raw_value || 0) <= 0 }]">
        <div class="text-sm w-full">
            <p class="text-lg font-bold mb-1">{{ trans('Sales') }}</p>
            <p class="flex flex-col">
                <span class="text-2xl font-bold">
                    {{ props.data.sales?.[props.interval].formatted_value || 0 }}
                    <span v-if="getYoYComparison('sales')" :class="['italic text-base font-medium ml-1', { 'text-green-500': getYoYComparison('sales')?.isPositive, 'text-red-500': getYoYComparison('sales')?.isNegative }]">
                        {{ getYoYComparison('sales')?.value }}
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
