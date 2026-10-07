<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2025, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import AnalyticsRetentionNotice from "@/Components/DataDisplay/AnalyticsRetentionNotice.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import TableWebsiteVisitors from "@/Components/Tables/Grp/Org/Web/TableWebsiteVisitors.vue"
import { PageHeadingTypes } from "@/types/PageHeading"

defineProps<{
    pageHead: PageHeadingTypes
    title: string
    retentionDays: number
    data: object
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <AnalyticsRetentionNotice
        :summary="ctrans('Showing the last :days days of visits.', { days: retentionDays })"
        :detail="ctrans('Older visits are deleted every night.')" />

    <TableWebsiteVisitors :data="data" />
</template>
