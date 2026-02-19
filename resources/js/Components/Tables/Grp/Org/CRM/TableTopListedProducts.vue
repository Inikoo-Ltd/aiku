<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"

defineProps<{
    data: object
    tab?: string
}>()
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5"></Table>
</template>
