<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import TableTariffCodes from "@/Components/Tables/Grp/Goods/TableTariffCodes.vue"
import { PageHeadingTypes } from "@/types/PageHeading"

defineProps<{
    data: object
    title: string
    pageHead: PageHeadingTypes
    canEdit: boolean
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <TableTariffCodes :data="data" :canEdit="canEdit" />
</template>
