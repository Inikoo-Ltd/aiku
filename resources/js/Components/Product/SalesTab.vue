<!--
  Sales Tab - Combines Sales Analytics and Sales Table
  Shows analytics overview at the top and detailed invoice table below
-->

<script setup lang="ts">
import SalesAnalytics from './SalesAnalytics.vue'
import ProductSales from './ProductSales.vue'

const props = defineProps<{
    data: object
    tab?: string
    salesData?: object
}>()
</script>

<template>
    <div>
        <!-- Sales Analytics Overview -->
        <SalesAnalytics v-if="salesData" :salesData="salesData" />

        <!-- Detailed Sales Table -->
        <div class="px-6 pb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Invoice Details</h3>
            <ProductSales :data="data" :tab="tab" />
        </div>
    </div>
</template>
