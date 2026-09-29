<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TableAssignableSuppliers from "@/Components/Tables/Grp/SupplyChain/TableAssignableSuppliers.vue"
import { capitalize } from "@/Composables/capitalize"

defineProps<{
    pageHead: object
    title: string
    data: object
    agent: {
        id: number
        code: string
        name: string
    }
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <TableAssignableSuppliers :data="data" :agent="agent" />
</template>
