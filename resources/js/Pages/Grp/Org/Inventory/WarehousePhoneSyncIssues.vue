<!--
  -  Author: Louis Perez
  -  Created: Thu, 26 Nov 2026
  -  Copyright (c) 2026
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TableWarehousePhoneSyncIssues from "@/Components/Tables/Grp/Org/Inventory/TableWarehousePhoneSyncIssues.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faMobileAlt } from "@fal"

library.add(faMobileAlt)

defineProps<{
    title: string
    pageHead: PageHeadingTypes
    issues: {}
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <TableWarehousePhoneSyncIssues :data="issues" tab="phone_sync_issues" class="mt-5" />
</template>
