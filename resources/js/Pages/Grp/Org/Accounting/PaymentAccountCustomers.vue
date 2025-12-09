<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from '@/types/PageHeading'
import TablePaymentAccountCustomers from '@/Components/Tables/Grp/Accounting/TablePaymentAccountCustomers.vue'
import { Table as TableTS } from '@/types/Table'

defineProps<{
    title: string,
    pageHead: PageHeadingTypes
    data: TableTS

}>()

</script>


<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <TablePaymentAccountCustomers :data />
</template>