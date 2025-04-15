<script setup lang="ts">
import Table from '@/Components/Table/Table.vue'
import type { Table as TableTS } from "@/types/Table"


defineProps<{
    data: TableTS,
}>()



</script>
<template>
     <Table :resource="data" >
    </Table>
</template>
