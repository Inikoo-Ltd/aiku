<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3"
import { capitalize } from "@/Composables/capitalize"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import Icon from "@/Components/Icon.vue"
import { useFormatTime } from "@/Composables/useFormatTime"

defineProps<{
    pageHead: any
    title: string
    data: any
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <Table :resource="data" class="mt-2">
        <template #cell(reference)="{ item }">
            <Link :href="route('grp.tickets.show', item.reference)" class="primaryLink">{{ item.reference }}</Link>
        </template>
        <template #cell(status)="{ item }">
            <Icon :data="item.status_icon" /> {{ item.status_label }}
        </template>
        <template #cell(subject)="{ item }">
            <span class="block truncate" :title="item.subject">{{ item.subject }}</span>
        </template>
        <template #cell(priority)="{ item }">
            <Icon :data="item.priority_icon" />
        </template>
        <template #cell(reporter)="{ item }">
            <span :title="item.customer ? `${item.reporter} · ${item.customer}` : item.reporter">{{ item.reporter_short || "-" }}</span>
        </template>
        <template #cell(assignee)="{ item }">
            <span :title="item.assignee">{{ item.assignee_username || "-" }}</span>
        </template>
        <template #cell(updated_at)="{ item }">
            <span :title="useFormatTime(item.updated_at, { formatTime: 'hm' })">{{ useFormatTime(item.updated_at, { formatTime: "d MMM HH:mm" }) }}</span>
        </template>
    </Table>
</template>
