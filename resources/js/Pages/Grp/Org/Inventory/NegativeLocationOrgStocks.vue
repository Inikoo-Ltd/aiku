<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Thu, 13 Aug 2026, Mijas, Spain
  -  Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TableNegativeLocationOrgStocks from "@/Components/Tables/Grp/Org/Inventory/TableNegativeLocationOrgStocks.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"

defineProps<{
    title: string
    pageHead: PageHeadingTypes
    negativeStocks: {}
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <TableNegativeLocationOrgStocks :data="negativeStocks" tab="negative_stocks" class="mt-5" />
</template>
