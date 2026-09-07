<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 08 Aug 2026 19:30:00 Central European Summer Time, Mijas, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import TableAgentSupplierPurchaseOrders from '@/Components/Tables/Grp/SupplyChain/TableAgentSupplierPurchaseOrders.vue'
import { capitalize } from '@/Composables/capitalize'

const props = defineProps<{
    pageHead: {}
    title: string
    data: {}
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <TableAgentSupplierPurchaseOrders :data="data" />
</template>
