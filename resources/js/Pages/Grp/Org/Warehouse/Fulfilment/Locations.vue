<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 23 May 2024 15:39:52 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import TableLocations from "@/Components/Tables/Grp/Org/Inventory/Fulfilment/TableLocations.vue"
import { capitalize } from "@/Composables/capitalize"
import { faWarehouse, faMapSigns } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { PageHeadingTypes } from "@/types/PageHeading";
library.add(faWarehouse, faMapSigns)

defineProps<{
    title: string
    pageHead: PageHeadingTypes
    data: {}

}>()

</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <TableLocations :data="data" :tab="'locations'" />
</template>
