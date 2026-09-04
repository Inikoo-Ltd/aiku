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
        <template #cell(priority)="{ item }">
            <Icon :data="item.priority_icon" /> {{ item.priority_label }}
        </template>
        <template #cell(reporter)="{ item }">
            {{ item.reporter || "-" }}<span v-if="item.customer" class="text-gray-400"> · {{ item.customer }}</span>
        </template>
        <template #cell(assignee)="{ item }">
            {{ item.assignee || "-" }}
        </template>
        <template #cell(updated_at)="{ item }">
            {{ useFormatTime(item.updated_at, { formatTime: "hm" }) }}
        </template>
    </Table>
</template>
