<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import TableCustomers from '@/Components/Tables/Grp/Org/CRM/TableCustomers.vue'
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from '@/types/PageHeading'

defineProps<{
    pageHead: PageHeadingTypes
    title: string
    customers: object
    tabs: { current: string, navigation: object }
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <TableCustomers :data="customers" :tab="tabs.current" />
</template>
