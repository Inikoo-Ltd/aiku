<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Tue, 28 Feb 2023 10:07:36 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TablePaymentAccounts from '@/Components/Tables/Grp/Org/Accounting/TablePaymentAccounts.vue'
import { capitalize } from "@/Composables/capitalize"
import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
import { Shop } from "@/types/shop"

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    data: {
        data: {}
    }
    shops_list: {
        data: Shop[]
    }
}>()

</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <TablePaymentAccounts :data="data" />
</template>
