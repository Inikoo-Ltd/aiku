<!--
  -  Author: Louis Perez
  -  Created: Thu, 26 Nov 2026
  -  Copyright (c) 2026
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import { useFormatTime } from "@/Composables/useFormatTime"

defineProps<{
    data: object
    tab?: string
}>()

interface PhoneSyncIssue {
    since: string | null
    performed_at: string | null
    staff: string | null
    device: string | null
    action: string
    record: string | null
    record_sub: string | null
    reason: string | null
    code: string | null
    code_url: string | null
    task: string | null
    task_url: string | null
    state_label: string
    is_open: boolean
}
</script>

<template>
    <Table :resource="data" :name="tab ?? 'phone_sync_issues'">
        <template #cell(since)="{ item: issue }">
            <span class="whitespace-nowrap tabular-nums" :title="(issue as PhoneSyncIssue).performed_at ?? undefined">
                {{ (issue as PhoneSyncIssue).since ? useFormatTime(new Date((issue as PhoneSyncIssue).since as string), { formatTime: "short-datetime" }) : "-" }}
            </span>
        </template>

        <template #cell(device)="{ item: issue }">
            <span class="text-gray-700">{{ (issue as PhoneSyncIssue).device ?? "-" }}</span>
        </template>

        <template #cell(record)="{ item: issue }">
            <div class="whitespace-nowrap">{{ (issue as PhoneSyncIssue).record ?? "-" }}</div>
            <div v-if="(issue as PhoneSyncIssue).record_sub" class="text-xs text-gray-500">{{ (issue as PhoneSyncIssue).record_sub }}</div>
        </template>

        <template #cell(reason)="{ item: issue }">
            <div class="truncate" :title="(issue as PhoneSyncIssue).reason ?? undefined">{{ (issue as PhoneSyncIssue).reason ?? "-" }}</div>
            <a v-if="(issue as PhoneSyncIssue).code_url" :href="(issue as PhoneSyncIssue).code_url as string" target="_blank" rel="noopener" class="primaryLink font-mono text-xs">
                {{ (issue as PhoneSyncIssue).code }}
            </a>
        </template>

        <template #cell(task)="{ item: issue }">
            <a v-if="(issue as PhoneSyncIssue).task_url" :href="(issue as PhoneSyncIssue).task_url as string" class="primaryLink whitespace-nowrap">{{ (issue as PhoneSyncIssue).task }}</a>
            <span v-else class="text-gray-400">-</span>
        </template>

        <template #cell(state)="{ item: issue }">
            <span
                class="inline-flex whitespace-nowrap rounded px-2 py-0.5 text-xs font-medium"
                :class="(issue as PhoneSyncIssue).is_open ? 'bg-amber-50 text-amber-800' : 'bg-green-50 text-green-700'">
                {{ (issue as PhoneSyncIssue).state_label }}
            </span>
        </template>
    </Table>
</template>
