<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import TableSalesChannels from "@/Components/Tables/Grp/SalesChannels/TableSalesChannels.vue"
import { PageHeadingTypes } from '@/types/PageHeading'

defineProps<{
    title: string
    pageHead: PageHeadingTypes
    data: {}
}>()

</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <TableSalesChannels :data="data" />
</template>
