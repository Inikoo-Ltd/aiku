<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 08 Aug 2026 20:00:00 Central European Summer Time, Mijas, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TableSelectSuppliers from "@/Components/Tables/Grp/Org/Procurement/TableSelectSuppliers.vue"
import { capitalize } from "@/Composables/capitalize"

const props = defineProps<{
    pageHead: {}
    title: string
    data: {}
    organisation_id: number
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <TableSelectSuppliers :data="data" :organisation_id="organisation_id" />
</template>
