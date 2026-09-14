<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import { capitalize } from "@/Composables/capitalize"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import Icon from "@/Components/Icon.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLiveTickets } from "@/Composables/useLiveTickets"
import TicketsCreatedInterval from "@/Components/Tickets/TicketsCreatedInterval.vue"

defineProps<{
    pageHead: any
    title: string
    data: any
    createdIntervals: Record<string, string>
    createdInterval: string
    searchHelp: string[]
}>()

useLiveTickets(["data"])
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <TicketsCreatedInterval :options="createdIntervals" :selected="createdInterval" class="mx-4 mt-2" />
    <div class="mx-4 mt-1 flex flex-wrap items-center gap-1 text-xs text-gray-400">
        <span class="mr-1">{{ trans("Search tips") }}:</span>
        <code v-for="tip in searchHelp" :key="tip" class="rounded bg-gray-100 px-1.5 py-0.5 text-gray-500">{{ tip }}</code>
    </div>
    <Table :resource="data" class="mt-2">
        <template #cell(reference)="{ item }">
            <Link :href="route('grp.tickets.show', item.reference)" class="primaryLink">{{ item.reference }}</Link>
        </template>
        <template #cell(status)="{ item }">
            <Icon :data="item.status_icon" /> {{ item.status_label }}
        </template>
        <template #cell(subject)="{ item }">
            <span class="block truncate" :title="item.subject">{{ item.subject }}</span>
            <span v-if="item.search_snippet" class="block truncate text-xs text-gray-500 [&_mark]:rounded [&_mark]:bg-yellow-200 [&_mark]:px-0.5" v-html="item.search_snippet" />
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
