<script setup lang="ts">
import { computed } from "vue";
import PureMultiplePriceCurrencyTable from "./PureMultiplePriceCurrencyTable.vue";
import OrgCostPopover from "./Supports/OrgCostPopover.vue";

interface CurrencyRate {
    currency: string
    currency_symbol?: string
    currency_id: number
    ratio_gbp: number | null
    ratio_eur: number | null
}

interface CurrencyPrice {
    value: number | null
    independent: boolean
}

const props = defineProps<{
    modelValue: Record<string, CurrencyPrice> | null
    currencies: Record<string, CurrencyRate>
    unitsPerOuter?: number
    avg_org_cost?: number | null
    org_data?: Record<string, any> | null
}>();

defineEmits<{
    (e: 'update:modelValue', value: Record<string, CurrencyPrice>): void
}>();

const costs = computed(() => {
    const result: Record<string, number | null> = {};

    if (props.avg_org_cost == null) {
        return result;
    }

    for (const [code, rate] of Object.entries(props.currencies ?? {})) {
        result[code] = rate.ratio_eur == null
            ? null
            : Math.round(props.avg_org_cost * rate.ratio_eur * 100) / 100;
    }

    return result;
});
</script>

<template>
    <PureMultiplePriceCurrencyTable
        :model-value="modelValue"
        :currencies="currencies"
        :units-per-outer="unitsPerOuter"
        :costs="costs"
        label="Price"
        color="blue"
        edit-on="outer"
        margin-label="Margin"
        show-cost
        cost-label="Avg Org Cost"
        @update:model-value="$emit('update:modelValue', $event)"
    >
        <template #cost-suffix="{ currency }">
            <OrgCostPopover :org_data="org_data" :currency="currency" />
        </template>
    </PureMultiplePriceCurrencyTable>
</template>
