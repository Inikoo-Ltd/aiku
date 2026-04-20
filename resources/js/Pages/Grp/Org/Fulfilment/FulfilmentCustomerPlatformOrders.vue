<script setup lang="ts">
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TableFulfilmentCustomerPlatformOrders from "@/Components/Tables/Grp/Org/Fulfilment/TableFulfilmentCustomerPlatformOrders.vue"
import { Head } from "@inertiajs/vue3"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import type { Table as TableTS } from "@/types/Table"

defineProps<{
    data: TableTS
    title: string
    pageHead: PageHeadingTypes
    tabs: {
        current: string
        navigation: {}
    }
    platforms: {
        data: any
    }
    attachRoute: {}
}>()


</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <TableFulfilmentCustomerPlatformOrders :data="data" />

</template>
