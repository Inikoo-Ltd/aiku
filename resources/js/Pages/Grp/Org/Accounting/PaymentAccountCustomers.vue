<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'

import { capitalize } from "@/Composables/capitalize"

import { PageHeading as TSPageHeading } from '@/types/PageHeading'

import TablePaymentAccountCustomers from '@/Components/Tables/Grp/Accounting/TablePaymentAccountCustomers.vue'
import { Table as TableTS } from '@/types/Table'

const props = defineProps<{
    title: string,
    pageHead: TSPageHeading
    data: TableTS

}>()

</script>


<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <TablePaymentAccountCustomers :data />
</template>