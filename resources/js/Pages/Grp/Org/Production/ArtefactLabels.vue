<!--
  - Author: Vika Aqordi <aqordeon@gmail.com>
  - Created: Tue, 16 Sep 2026, Bali, Indonesia
  - Copyright (c) 2026, Inikoo LTD
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TableArtefactLabels from "@/Components/Tables/Grp/Org/Production/TableArtefactLabels.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"

defineProps<{
    pageHead: PageHeadingTypes
    title: string
    data: object
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <TableArtefactLabels :data="data" />
</template>
