<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 02 Sep 2026 Malaga, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TableArtefactFamilies from "@/Components/Tables/Grp/Org/Production/TableArtefactFamilies.vue"
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
    <TableArtefactFamilies :data="data" />
</template>
