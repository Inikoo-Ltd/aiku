<!--
  -  Author: Nickel
  -  Created: Tue, 01 Apr 2026
  -  Copyright (c) 2026, Inikoo LTD
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TableOrgStockHistory from "@/Components/Tables/Grp/Org/Inventory/TableOrgStockHistory.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"

defineProps<{
    title: string
    pageHead: PageHeadingTypes
    data: {}
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <TableOrgStockHistory :data="data" />
</template>
