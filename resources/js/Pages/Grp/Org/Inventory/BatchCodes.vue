<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Created: Mon, 21 Apr 2026, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TableBatchCodes from "@/Components/Tables/Grp/Org/Inventory/TableBatchCodes.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"

defineProps<{
    title: string
    pageHead: PageHeadingTypes
    data: object
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <TableBatchCodes :data="data" />
</template>
