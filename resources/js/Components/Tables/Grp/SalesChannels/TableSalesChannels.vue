<script setup lang="ts">
import Table from '@/Components/Table/Table.vue'

const props = defineProps<{
    data: {}
}>()
</script>

<template>
    <Table :resource="data" class="mt-5"></Table>
</template>
