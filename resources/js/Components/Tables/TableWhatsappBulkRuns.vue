<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import Tag from "primevue/tag"
import { useFormatTime } from "@/Composables/useFormatTime"

defineProps<{
    data: object
    tab?: string
}>()

const stateSeverity = (state: string) => ({
    in_process: "secondary",
    sending: "info",
    sent: "success",
    stopped: "danger",
}[state] ?? "secondary")
</script>

<template>
    <Table :resource="data" :name="tab">
        <template #cell(created_at)="{ item: bulkRun }">
            <span class="whitespace-nowrap">{{ useFormatTime(bulkRun.created_at, { formatTime: "aiku" }) }}</span>
        </template>
        <template #cell(state)="{ item: bulkRun }">
            <Tag :severity="stateSeverity(bulkRun.state)" :value="bulkRun.state_label" />
        </template>
        <template #cell(number_failed)="{ item: bulkRun }">
            <span :class="bulkRun.number_failed ? 'text-red-600' : ''">{{ bulkRun.number_failed }}</span>
        </template>
        <template #cell(sent_at)="{ item: bulkRun }">
            <span class="whitespace-nowrap">{{ bulkRun.sent_at ? useFormatTime(bulkRun.sent_at, { formatTime: "aiku" }) : "-" }}</span>
        </template>
    </Table>
</template>
