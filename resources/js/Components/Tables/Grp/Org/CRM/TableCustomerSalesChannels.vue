<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from '@/Components/Table/Table.vue'
import type { Table as TableTS } from "@/types/Table"
import { RouteParams } from "@/types/route-params";
import { Platform } from "@/types/platform";

defineProps<{
    data: TableTS,
}>()

</script>
<template>
     <Table :resource="data" >
    </Table>
</template>
