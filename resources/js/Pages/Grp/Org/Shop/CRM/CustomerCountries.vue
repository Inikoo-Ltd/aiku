<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import TableCustomerCountries from '@/Components/Tables/Grp/Org/CRM/TableCustomerCountries.vue'
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from '@/types/PageHeading'

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    data: {}
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <TableCustomerCountries :data="data" />
</template>
