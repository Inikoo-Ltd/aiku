<script setup lang="ts">
import ShopSalesVsRefunds from "@/Components/Shop/ShopSalesVsRefunds.vue";
import StatsBoxIntervals from "@/Components/Stats/StatsBoxIntervals.vue";

const props = defineProps<{
    interval: string
    data: any
}>()
</script>

<template>
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4 p-4">
        <ShopSalesVsRefunds :interval="interval" :data="data.interval_data" />
        <StatsBoxIntervals
            v-if="data.stats_box"
            :stat="data.stats_box"
            :interval="interval"
        />
    </div>
</template>
