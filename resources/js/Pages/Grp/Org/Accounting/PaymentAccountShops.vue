<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 16 Dec 2025 19:39:25 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TablePaymentAccountShops from '@/Components/Tables/Grp/Org/Accounting/TablePaymentAccountShops.vue'
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from '@/types/PageHeading'
import { Shop } from "@/types/shop"

defineProps<{
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
    <TablePaymentAccountShops :data="data"/>
</template>
