<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import TableUpcomingTransactions from '@/Components/Tables/Grp/Org/Ordering/TableUpcomingTransactions.vue'


const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    data: {}
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <TableUpcomingTransactions :data="data" />
</template>
