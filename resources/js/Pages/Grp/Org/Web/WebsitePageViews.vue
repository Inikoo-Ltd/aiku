<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import AnalyticsRetentionNotice from "@/Components/DataDisplay/AnalyticsRetentionNotice.vue"
import TableWebsitePageViews from "@/Components/Tables/Grp/Org/Web/TableWebsitePageViews.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
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
        :summary="ctrans('Showing the last :days days of page views.', { days: retentionDays })"
        :detail="ctrans('Older page views are deleted every night.')" />

    <TableWebsitePageViews :data="data" />
</template>
