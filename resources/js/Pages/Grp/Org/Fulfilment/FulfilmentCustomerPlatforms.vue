
<script setup lang="ts">
import PageHeading from "@/Components/Headings/PageHeading.vue";
import TableFulfilmentCustomerPlatforms from "@/Components/Tables/Grp/Org/Fulfilment/TableFulfilmentCustomerPlatforms.vue"
import { Head } from "@inertiajs/vue3";
import { PageHeading as PageHeadingTS } from '@/types/PageHeading'
import { capitalize } from "@/Composables/capitalize"

const props = defineProps<{
    data:{}
    title: string
    pageHead: object
    tabs: {
        current: string
        navigation: {}
    }
}>()
</script>

<template>
     <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
   <TableFulfilmentCustomerPlatforms :data="data" />
</template>
