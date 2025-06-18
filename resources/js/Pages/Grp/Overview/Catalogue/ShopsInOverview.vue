<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 15 Jun 2025 21:39:20 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import TableShopsInOverview from "@/Components/Tables/Grp/Org/Catalogue/TableShopsInOverview.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading"

defineProps<{
    pageHead: PageHeadingTypes
    title: string
    data: object
}>()


</script>

<template>
    <Head :title="capitalize(title)"/>
    <PageHeading :data="pageHead"></PageHeading>
    <TableShopsInOverview :data="data" />
</template>

