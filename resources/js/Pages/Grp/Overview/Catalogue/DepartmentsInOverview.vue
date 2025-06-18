<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 15 Jun 2025 14:17:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import TableDepartmentsInOverview from "@/Components/Tables/Grp/Org/Catalogue/TableDepartmentsInOverview.vue"
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
    <TableDepartmentsInOverview :data="data" />
</template>

