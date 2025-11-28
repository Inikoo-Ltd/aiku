<script setup lang="ts">
import { inject, computed } from "vue"
import { trans } from "laravel-vue-i18n"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"

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

const getYoYComparison = (metric: string) => {
    const delta = props.tableData?.tables?.invoice_categories?.totals?.columns?.[`${metric}_delta`]?.[props.intervals.value];
    if (!delta || delta.raw_value === 9999999) return null;

    return {
        value: delta.formatted_value,
        isPositive: delta.raw_value > 1,
        isNegative: delta.raw_value < 1
    };
}
</script>

<template>
    <div :class="['flex items-center gap-4 p-4 bg-gray-50 border shadow-sm rounded-lg', { hidden: (props.tableData?.tables?.invoice_categories?.totals?.columns?.[props.scope === 'group' ? 'sales_grp_currency' : 'sales_org_currency']?.[props.intervals.value]?.raw_value || 0) <= 0 }]">
        <div class="text-sm w-full">
            <p class="text-lg font-bold mb-1">{{ trans('Sales') }}</p>
            <p class="flex flex-col">
                <span class="text-2xl font-bold">
                    {{ props.tableData?.tables?.invoice_categories?.totals?.columns?.[props.scope === 'group' ? 'sales_grp_currency' : 'sales_org_currency']?.[props.intervals.value]?.formatted_value || 0 }}
                    <span v-if="getYoYComparison(props.scope === 'group' ? 'sales_grp_currency' : 'sales_org_currency')" :class="['italic text-base font-medium ml-1', { 'text-green-500': getYoYComparison(props.scope === 'group' ? 'sales_grp_currency' : 'sales_org_currency')?.isPositive, 'text-red-500': getYoYComparison(props.scope === 'group' ? 'sales_grp_currency' : 'sales_org_currency')?.isNegative }]">
                        {{ getYoYComparison(props.scope === 'group' ? 'sales_grp_currency' : 'sales_org_currency')?.value }}
                    </span>
                </span>
                <span>
                    {{ refundRatio.toFixed(2) }}%
                    <span class="italic">{{ trans("refunded") }}</span>
                </span>
            </p>
        </div>
    </div>
</template>
