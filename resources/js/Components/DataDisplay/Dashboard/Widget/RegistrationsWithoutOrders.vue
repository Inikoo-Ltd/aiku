<script setup lang="ts">
import { inject, computed } from "vue"
import { trans } from "laravel-vue-i18n"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"

const props = defineProps<{
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

const totalsColumns = computed(() => {
    // Group dashboard uses organisations table, Organisation dashboard uses shops table
    if (props.tableData?.tables?.organisations?.totals?.columns) {
        return props.tableData.tables.organisations.totals.columns
    }
    if (props.tableData?.tables?.shops?.totals?.columns) {
        return props.tableData.tables.shops.totals.columns
    }
    return null
})

const registrationsRatio = computed(() => {
    const with_orders = Number(
        totalsColumns.value?.registrations_with_orders?.[props.intervals.value]?.raw_value || 0
    )
    const without_orders = Number(
        totalsColumns.value?.registrations_without_orders?.[props.intervals.value]?.raw_value || 0
    )

    const total = with_orders + without_orders
    return total > 0 ? (without_orders / total) * 100 : 0
})

const getYoYComparison = (metric: string) => {
    const delta = totalsColumns.value?.[`${metric}_delta`]?.[props.intervals.value];
    if (!delta || delta.raw_value === 9999999) return null;

    return {
        value: delta.formatted_value,
        isPositive: delta.raw_value > 1,
        isNegative: delta.raw_value < 1
    };
}
</script>

<template>
    <div class="flex items-center gap-4 p-4 bg-gray-50 border shadow-sm rounded-lg">
        <div class="text-sm w-full">
            <p class="text-lg font-bold mb-1">{{ trans('Registrations without Orders') }}</p>
            <p class="flex flex-col">
                <span class="text-2xl font-bold">
                    {{ totalsColumns?.registrations_without_orders?.[props.intervals.value]?.formatted_value || 0 }}
                    <span v-if="getYoYComparison('registrations_without_orders')" :class="['italic text-base font-medium ml-1', { 'text-green-500': getYoYComparison('registrations_without_orders')?.isPositive, 'text-red-500': getYoYComparison('registrations_without_orders')?.isNegative }]">
                        {{ getYoYComparison('registrations_without_orders')?.value }}
                    </span>
                </span>
                <span>
                    {{ registrationsRatio.toFixed(1) }}%
                    <span class="italic">{{ trans("of total registrations") }}</span>
                </span>
            </p>
        </div>
    </div>
</template>
