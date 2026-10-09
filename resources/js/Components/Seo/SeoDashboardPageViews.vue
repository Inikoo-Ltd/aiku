<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import AnalyticsRetentionNotice from "@/Components/DataDisplay/AnalyticsRetentionNotice.vue"
import TableWebsitePageViews from "@/Components/Tables/Grp/Org/Web/TableWebsitePageViews.vue"
import { ctrans } from "@/Composables/useTrans"

defineOptions({ inheritAttrs: false })

defineProps<{
    data: { table: object, retention_days: number }
    tab: string
}>()
</script>

<template>
    <div>
        <AnalyticsRetentionNotice
            :summary="ctrans('Showing the last :days days of page views, whatever the interval above.', { days: data.retention_days })"
            :detail="ctrans('Older page views are deleted every night.')" />
        <TableWebsitePageViews :data="data.table" :tab="tab" />
    </div>
</template>
