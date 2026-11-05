<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 05 Nov 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TableMasterAssetCompetitorProducts from "@/Components/Tables/Grp/Masters/TableMasterAssetCompetitorProducts.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"

defineProps<{
    pageHead: PageHeadingTypes
    title: string
    data: object
    can_edit: boolean
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <TableMasterAssetCompetitorProducts :data="data" :canEdit="can_edit" />
</template>
