<script setup lang="ts">
import ShopSalesVsRefunds from "@/Components/Shop/ShopSalesVsRefunds.vue";

const props = defineProps<{
    interval: string
    data: any
}>()
</script>

<template>
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4 px-4">
        <ShopSalesVsRefunds :interval="interval" :data="data" />
    </div>
</template>
