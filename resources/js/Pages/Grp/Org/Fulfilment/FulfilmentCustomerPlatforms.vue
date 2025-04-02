
<script setup lang="ts">
import TableFulfilmentCustomerPlatforms from "@/Components/Tables/Grp/Org/Fulfilment/TableFulfilmentCustomerPlatforms.vue"
const props = defineProps<{
    data:{}
}>()
</script>

<template>
   <TableFulfilmentCustomerPlatforms :data="data" />
</template>
