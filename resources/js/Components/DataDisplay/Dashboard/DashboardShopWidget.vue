<script setup lang="ts">
import ShopSales from "@/Components/Shop/ShopSales.vue";
import StatsBoxIntervals from "@/Components/Stats/StatsBoxIntervals.vue";
import ShopInvoices from "@/Components/Shop/ShopInvoices.vue";

const props = defineProps<{
    interval: string
    data: any
}>()
</script>

<template>
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4 p-4">
        <ShopSales :interval="interval" :data="data.interval_data" />
        <ShopInvoices :interval="interval" :data="data.interval_data" />
<!--        <StatsBoxIntervals v-if="data.stats_box" :stat="data.stats_box" :interval="interval" />-->
    </div>
</template>
