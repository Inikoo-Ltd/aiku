<!--
  -  Author: stewicca <stewicalf@gmail.com>
  -  Created: Fri, 17 Jul 2026, Bali, Indonesia
  -  Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TableOrgStockLowStockAudits from "@/Components/Tables/Grp/Org/Inventory/TableOrgStockLowStockAudits.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"

defineProps<{
    title: string
    pageHead: PageHeadingTypes
    lowStockAudits: {}
    auditRoute: routeType
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <TableOrgStockLowStockAudits
        :data="lowStockAudits"
        :auditRoute="auditRoute"
        tab="low_stock_audits"
        class="mt-5"
    />
</template>
