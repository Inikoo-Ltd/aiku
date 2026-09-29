<script setup lang="ts">
import PureMultiplePriceCurrencyTable from "./PureMultiplePriceCurrencyTable.vue";

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

withDefaults(defineProps<{
    modelValue: Record<string, CurrencyPrice> | null
    currencies: Record<string, CurrencyRate>
    unitsPerOuter?: number
    costs?: Record<string, number | null>
    editOn?: 'outer' | 'unit'
    autoMultiplier?: number
}>(), {
    editOn: 'unit',
    autoMultiplier: 2.4,
});

defineEmits<{
    (e: 'update:modelValue', value: Record<string, CurrencyPrice>): void
}>();
</script>

<template>
    <PureMultiplePriceCurrencyTable
        :model-value="modelValue"
        :currencies="currencies"
        :units-per-outer="unitsPerOuter"
        :costs="costs"
        label="RRP"
        color="purple"
        :edit-on="editOn"
        margin-label="Margin"
        :always-independent-currency-codes="[]"
        auto-from-cost
        :auto-multiplier="autoMultiplier"
        @update:model-value="$emit('update:modelValue', $event)"
    />
</template>
