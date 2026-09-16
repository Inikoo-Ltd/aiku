<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 16 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import { capitalize } from "@/Composables/capitalize"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import Icon from "@/Components/Icon.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { PageHeadingTypes } from "@/types/PageHeading"

defineProps<{
    pageHead: PageHeadingTypes
    title: string
    data: object
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <Table :resource="data" class="mt-5">
        <template #cell(status)="{ item }">
            <span class="inline-flex items-center gap-1" :title="item.status_label"><Icon :data="item.status_icon" /> {{ item.status_label }}</span>
        </template>
        <template #cell(priority)="{ item }">
            <div class="flex justify-center" :title="item.priority_label"><Icon :data="item.priority_icon" /></div>
        </template>
        <template #cell(requester)="{ item }">
            {{ item.requester_name || "-" }}
        </template>
        <template #cell(assignee)="{ item }">
            {{ item.assignee_name || item.department_label || "-" }}
        </template>
        <template #cell(due_at)="{ item }">
            {{ item.due_at ? useFormatTime(item.due_at, { formatTime: "mdy" }) : "-" }}
        </template>
        <template #cell(created_at)="{ item }">
            {{ useFormatTime(item.created_at, { formatTime: "hm" }) }}
        </template>
        <template #cell(closed_at)="{ item }">
            {{ item.closed_at ? useFormatTime(item.closed_at, { formatTime: "hm" }) : "-" }}
        </template>
    </Table>
</template>
