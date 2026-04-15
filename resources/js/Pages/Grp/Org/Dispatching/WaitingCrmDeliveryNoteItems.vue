<!--
  - Author: Steven Wicca stewicalf@gmail.com
  - Created: Wed, 15 Apr 2026 00:00:00 Central Indonesia Time, Bali, Indonesia
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from '@/types/PageHeading'
import TableWaitingCrmDeliveryNoteItems from '@/Components/Tables/Grp/Org/Dispatching/TableWaitingCrmDeliveryNoteItems.vue'
import type { Table as TableTS } from '@/types/Table'

defineProps<{
    title: string
    pageHead: PageHeadingTypes
    items: TableTS
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <TableWaitingCrmDeliveryNoteItems :data="items" />
</template>
