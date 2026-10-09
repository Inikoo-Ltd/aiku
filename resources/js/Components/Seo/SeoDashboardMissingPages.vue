<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import TableWebsiteNotFoundPaths from "@/Components/Tables/Grp/Org/Web/TableWebsiteNotFoundPaths.vue"
import { ctrans } from "@/Composables/useTrans"

defineOptions({ inheritAttrs: false })

defineProps<{
    data: { table: object, website: { id: number, domain: string, url: string }, retention_days: number, can_edit: boolean }
    tab: string
}>()
</script>

<template>
    <div>
        <p class="mx-4 mb-2 mt-4 max-w-3xl text-sm text-gray-600">
            {{ ctrans("URLs on :domain that people opened but that have no page, whatever the interval above. Redirect the ones with many hits or backlinks to the page they were meant to reach. Paths not seen for :days days are removed.", { domain: data.website.domain, days: data.retention_days }) }}
        </p>
        <TableWebsiteNotFoundPaths :data="data.table" :website="data.website" :canEdit="data.can_edit" :tab="tab" />
    </div>
</template>
