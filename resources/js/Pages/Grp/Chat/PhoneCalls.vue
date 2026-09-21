<!--
  - Author: Andi Ferdiawan <dev@aw-advantage.com>
  - Copyright (c) 2026, Andi Ferdiawan
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faUser, faUserSecret } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"

defineProps<{
    title: string
    pageHead: object
    data: object
}>()

const STATUS_CLASS: Record<string, string> = {
    in_progress: "bg-emerald-50 text-emerald-700 ring-emerald-200",
    completed: "bg-gray-50 text-gray-600 ring-gray-200",
    cancelled: "bg-gray-50 text-gray-400 ring-gray-200",
    auto_closed: "bg-amber-50 text-amber-700 ring-amber-200",
}

const formatDuration = (seconds: number | null) => {
    if (!seconds && seconds !== 0) {
        return "—"
    }

    const minutes = Math.floor(seconds / 60)
    const rest = seconds % 60

    return minutes > 0 ? `${minutes}m ${rest}s` : `${rest}s`
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <Table :resource="data" name="phone_calls" class="mt-5">
        <template #cell(started_at)="{ item: call }">
            <span class="whitespace-nowrap">{{ useFormatTime(call.started_at, { formatTime: "short-datetime" }) }}</span>
        </template>

        <template #cell(contact)="{ item: call }">
            <div v-if="call.contact_name || call.contact_type" class="flex items-center gap-2">
                <FontAwesomeIcon :icon="call.contact_type === 'customer' ? faUser : faUserSecret"
                    class="text-xs text-gray-400" v-tooltip="call.contact_label" />
                <span class="truncate">{{ call.contact_name || call.contact_label }}</span>
            </div>
            <span v-else class="text-gray-400">—</span>
        </template>

        <template #cell(duration)="{ item: call }">
            <span class="tabular-nums whitespace-nowrap">{{ formatDuration(call.duration_seconds) }}</span>
        </template>

        <template #cell(status)="{ item: call }">
            <span class="inline-flex rounded-full px-2 py-0.5 text-xs ring-1 ring-inset"
                :class="STATUS_CLASS[call.status] ?? 'bg-gray-50 text-gray-600 ring-gray-200'">
                {{ call.status_label }}
            </span>
        </template>

        <template #cell(notes)="{ item: call }">
            <p v-if="call.notes" class="max-w-xl whitespace-pre-line text-gray-600">{{ call.notes }}</p>
            <span v-else class="text-gray-400 italic">
                {{ call.status === 'in_progress' ? ctrans("Still on the phone") : ctrans("Nothing was written down") }}
            </span>
        </template>
    </Table>
</template>
