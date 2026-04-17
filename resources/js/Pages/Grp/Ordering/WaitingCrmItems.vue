<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 13 Apr 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from '@/types/PageHeading'
import TableWaitingCrmItems from '@/Components/Tables/Grp/Org/Ordering/TableWaitingCrmItems.vue'
import type { Table as TableTS } from '@/types/Table'

defineProps<{
    title: string
    pageHead: PageHeadingTypes
    waiting_crm_items: TableTS
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <TableWaitingCrmItems :data="waiting_crm_items" />
</template>
