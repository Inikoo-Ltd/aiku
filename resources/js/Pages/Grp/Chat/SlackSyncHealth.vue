<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 21 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import { computed } from "vue"
import Chart from "primevue/chart"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheckCircle, faExclamationTriangle } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime, useRangeFromNow } from "@/Composables/useFormatTime"

type Warning = { type: string; since?: string | null; working_hours?: number | null; command?: string; started_at?: string | null; error?: string | null; count?: number }
type Run = { command: string; scheduled_at: string; status: string; started_at: string | null; finished_at: string | null; error: string | null; totals: Record<string, number | string> | null }

const props = defineProps<{
    title: string
    pageHead: object
    summary: {
        channels_kept: number
        channels_not_joined: number
        channels_archived: number
        channels_ignored: number
        messages: number
        messages_last_day: number
        newest_message_at: string | null
        people_active: number
        people_linked: number
        files: { state: string; files: number }[]
        files_total: number
        files_size: number
        files_in_bucket: number
        files_failed: number
        emojis: number
        reactions: number
        cached_seconds: number
    }
    runs: Run[]
    warnings: Warning[]
    data: object
    writing: {
        share: number | null
        previous_share: number | null
        messages: number
        from_aiku: number
        previous_messages: number
        previous_from_aiku: number
        people_connected: number
        people_writing_from_aiku: number
        weeks: { week: string; from_aiku: number; in_slack: number }[]
    }
    writing_channels: object
}>()

const number = (value: number) => value.toLocaleString()

const dateTime = (iso: string | null) => (iso ? useFormatTime(iso, { formatTime: "short-datetime" }) : "–")

const size = (bytes: number) => {
    const units = ["B", "KB", "MB", "GB", "TB"]
    let value = bytes
    let unit = 0
    while (value >= 1024 && unit < units.length - 1) {
        value /= 1024
        unit++
    }
    return `${value.toFixed(unit > 1 ? 1 : 0)} ${units[unit]}`
}

const warningText = (warning: Warning) => {
    switch (warning.type) {
        case "quiet":
            return warning.since
                ? ctrans("No message stored for :hours working hours, since :time. Check the Slack events webhook.", { hours: warning.working_hours ?? 0, time: dateTime(warning.since) })
                : ctrans("No Slack message is stored at all.")
        case "run_failed":
            return ctrans(":command failed on its last run (:time): :error", { command: warning.command ?? "", time: dateTime(warning.started_at ?? null), error: warning.error ?? "" })
        case "run_stuck":
            return ctrans(":command started at :time and never finished.", { command: warning.command ?? "", time: dateTime(warning.started_at ?? null) })
        case "run_late":
            return ctrans(":command has not run since :time.", { command: warning.command ?? "", time: dateTime(warning.started_at ?? null) })
        case "run_not_recorded":
            return ctrans(":command has no run recorded.", { command: warning.command ?? "" })
        case "files_failed":
            return ctrans("Files that failed to copy: :count", { count: warning.count ?? 0 })
    }
    return warning.type
}

const RUN_STATUS: Record<string, { label: () => string; class: string }> = {
    ok: { label: () => ctrans("OK"), class: "bg-green-50 text-green-700 ring-green-200" },
    running: { label: () => ctrans("Running"), class: "bg-gray-50 text-gray-600 ring-gray-200" },
    not_recorded: { label: () => ctrans("No run recorded yet"), class: "bg-gray-50 text-gray-500 ring-gray-200" },
    late: { label: () => ctrans("Did not run"), class: "bg-amber-50 text-amber-700 ring-amber-200" },
    stuck: { label: () => ctrans("Did not finish"), class: "bg-red-50 text-red-700 ring-red-200" },
    failed: { label: () => ctrans("Failed"), class: "bg-red-50 text-red-700 ring-red-200" },
}

const CHANNEL_STATUS: Record<string, { label: () => string; class: string }> = {
    kept: { label: () => ctrans("Kept"), class: "bg-green-50 text-green-700 ring-green-200" },
    not_joined: { label: () => ctrans("Not joined"), class: "bg-amber-50 text-amber-700 ring-amber-200" },
    archived: { label: () => ctrans("Archived"), class: "bg-gray-50 text-gray-500 ring-gray-200" },
    ignored: { label: () => ctrans("Ignored"), class: "bg-gray-50 text-gray-500 ring-gray-200" },
    private: { label: () => ctrans("Private"), class: "bg-gray-50 text-gray-500 ring-gray-200" },
    deleted: { label: () => ctrans("Deleted in Slack"), class: "bg-gray-50 text-gray-500 ring-gray-200" },
}

const FILE_STATE: Record<string, () => string> = {
    copied: () => ctrans("Copied"),
    external: () => ctrans("External"),
    skipped: () => ctrans("Skipped"),
    not_available: () => ctrans("Not available"),
    failed: () => ctrans("Failed"),
    pending: () => ctrans("Pending"),
    copying: () => ctrans("Copying"),
}

const FROM_AIKU = "#7c5cff"
const IN_SLACK = "#e8a33d"

const shareText = (share: number | null) => (share === null ? "–" : `${share.toLocaleString()}%`)

const shareChange = computed(() => {
    const { share, previous_share } = props.writing
    return share === null || previous_share === null ? null : Math.round((share - previous_share) * 10) / 10
})

const line = (label: string, data: number[], colour: string) => ({ label, data, borderColor: colour, backgroundColor: colour, borderWidth: 1.5, fill: false, tension: 0, pointRadius: 2.5, pointHoverRadius: 4 })

const writingChart = computed(() => ({
    labels: props.writing.weeks.map((week) => useFormatTime(week.week, { formatTime: "d MMM" })),
    datasets: [
        line(ctrans("Written from aiku"), props.writing.weeks.map((week) => week.from_aiku), FROM_AIKU),
        line(ctrans("Written in Slack"), props.writing.weeks.map((week) => week.in_slack), IN_SLACK),
    ],
}))

const writingChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: "index", intersect: false },
    plugins: { legend: { position: "bottom", labels: { boxWidth: 12, color: "#4b5563" } } },
    scales: {
        x: { grid: { display: false }, ticks: { color: "#6b7280", maxRotation: 0 } },
        y: { beginAtZero: true, grid: { color: "#f3f4f6" }, ticks: { color: "#6b7280", precision: 0 } },
    },
}

const totalsText = (totals: Record<string, number | string> | null) =>
    totals ? Object.entries(totals).map(([key, value]) => `${key.replace(/_/g, " ")}: ${typeof value === "number" ? number(value) : value}`).join(" · ") : ""
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="space-y-4 p-4 text-sm text-gray-700">
        <div v-if="warnings.length" class="rounded-xl border border-red-200 bg-red-50 p-4">
            <div class="flex items-center gap-2 font-semibold text-red-800">
                <FontAwesomeIcon :icon="faExclamationTriangle" fixed-width aria-hidden="true" />
                {{ ctrans("Not everything is arriving") }}
            </div>
            <ul class="mt-2 list-disc space-y-1 pl-8 text-red-800">
                <li v-for="(warning, index) in warnings" :key="index" class="break-words">{{ warningText(warning) }}</li>
            </ul>
        </div>
        <div v-else class="flex items-center gap-2 rounded-xl border border-green-200 bg-green-50 p-4 font-semibold text-green-800">
            <FontAwesomeIcon :icon="faCheckCircle" fixed-width aria-hidden="true" />
            {{ ctrans("Everything is arriving") }}
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="text-xs text-gray-500">{{ ctrans("Channels kept and joined") }}</div>
                <div class="mt-1 text-3xl font-semibold tabular-nums text-gray-900">{{ number(summary.channels_kept) }}</div>
                <div class="mt-1 text-xs tabular-nums text-gray-500">
                    <span v-if="summary.channels_not_joined" class="text-amber-700">{{ ctrans(":count not joined", { count: number(summary.channels_not_joined) }) }} · </span>
                    {{ ctrans(":archived archived · :ignored ignored", { archived: number(summary.channels_archived), ignored: number(summary.channels_ignored) }) }}
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="text-xs text-gray-500">{{ ctrans("Messages stored") }}</div>
                <div class="mt-1 text-3xl font-semibold tabular-nums text-gray-900">{{ number(summary.messages) }}</div>
                <div class="mt-1 text-xs tabular-nums text-gray-500">{{ ctrans(":count in the last 24 hours", { count: number(summary.messages_last_day) }) }}</div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="text-xs text-gray-500">{{ ctrans("Newest message") }}</div>
                <div class="mt-1 text-xl font-semibold tabular-nums text-gray-900">{{ summary.newest_message_at ? useRangeFromNow(summary.newest_message_at, { addSuffix: true }) : "–" }}</div>
                <div class="mt-1 text-xs tabular-nums text-gray-500">{{ dateTime(summary.newest_message_at) }}</div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="text-xs text-gray-500">{{ ctrans("People linked to an aiku user") }}</div>
                <div class="mt-1 text-3xl font-semibold tabular-nums text-gray-900">{{ number(summary.people_linked) }}</div>
                <div class="mt-1 text-xs tabular-nums text-gray-500">{{ ctrans("of :count active Slack people", { count: number(summary.people_active) }) }}</div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:col-span-2">
                <div class="text-xs text-gray-500">{{ ctrans("Files") }}</div>
                <div class="mt-1 text-3xl font-semibold tabular-nums text-gray-900">{{ number(summary.files_total) }}</div>
                <div class="mt-1 text-xs tabular-nums text-gray-500">{{ ctrans(":size · :count in the bucket", { size: size(summary.files_size), count: number(summary.files_in_bucket) }) }}</div>
                <div class="mt-2 flex flex-wrap gap-1.5">
                    <span v-for="row in summary.files" :key="row.state" class="whitespace-nowrap rounded-full px-2 py-0.5 text-xs ring-1 ring-inset" :class="row.state === 'failed' ? 'bg-red-50 text-red-700 ring-red-200' : 'bg-gray-50 text-gray-600 ring-gray-200'">
                        {{ (FILE_STATE[row.state] ?? (() => row.state))() }} {{ number(row.files) }}
                    </span>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="text-xs text-gray-500">{{ ctrans("Custom emoji") }}</div>
                <div class="mt-1 text-3xl font-semibold tabular-nums text-gray-900">{{ number(summary.emojis) }}</div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="text-xs text-gray-500">{{ ctrans("Reactions") }}</div>
                <div class="mt-1 text-3xl font-semibold tabular-nums text-gray-900">{{ number(summary.reactions) }}</div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-4 py-2 text-xs font-medium text-gray-500">{{ ctrans("Nightly commands, last run") }}</div>
            <div class="divide-y divide-gray-100">
                <div v-for="run in runs" :key="run.command" class="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-2">
                    <span class="w-64 whitespace-nowrap font-mono text-xs text-gray-800">{{ run.command }}</span>
                    <span class="w-40 whitespace-nowrap rounded-full px-2 py-0.5 text-center text-xs ring-1 ring-inset" :class="RUN_STATUS[run.status]?.class">{{ RUN_STATUS[run.status]?.label() ?? run.status }}</span>
                    <span class="whitespace-nowrap text-xs tabular-nums text-gray-500">{{ run.started_at ? dateTime(run.started_at) : ctrans("daily at :time UTC", { time: run.scheduled_at }) }}</span>
                    <span v-if="run.error" class="min-w-0 flex-1 break-words text-xs text-red-700">{{ run.error }}</span>
                    <span v-else class="min-w-0 flex-1 text-xs tabular-nums text-gray-500">{{ totalsText(run.totals) }}</span>
                </div>
            </div>
        </div>

        <p class="text-xs text-gray-400">{{ ctrans("Message and reaction counts are refreshed every :minutes minutes; warnings are read live.", { minutes: Math.round(summary.cached_seconds / 60) }) }}</p>
    </div>

    <Table :resource="data">
        <template #cell(name)="{ item }">
            <span class="whitespace-nowrap font-medium text-gray-800">#{{ item.name }}</span>
        </template>

        <template #cell(status)="{ item }">
            <span class="whitespace-nowrap rounded-full px-2 py-0.5 text-xs ring-1 ring-inset" :class="CHANNEL_STATUS[item.status]?.class">{{ CHANNEL_STATUS[item.status]?.label() ?? item.status }}</span>
        </template>

        <template #cell(messages)="{ item }">
            <span class="tabular-nums">{{ number(item.messages) }}</span>
        </template>

        <template #cell(oldest)="{ item }">
            <span class="whitespace-nowrap">{{ item.oldest ? useFormatTime(item.oldest, { formatTime: "aiku" }) : "–" }}</span>
        </template>

        <template #cell(newest)="{ item }">
            <span class="whitespace-nowrap">{{ dateTime(item.newest) }}</span>
        </template>

        <template #cell(history_synced_at)="{ item }">
            <span class="whitespace-nowrap">{{ dateTime(item.history_synced_at) }}</span>
        </template>

        <template #cell(history_complete)="{ item }">
            <span v-if="item.history_complete" class="whitespace-nowrap text-green-700">{{ ctrans("Complete") }}</span>
            <span v-else-if="item.status === 'kept'" class="whitespace-nowrap text-amber-700">{{ ctrans("Not yet") }}</span>
            <span v-else class="text-gray-400">–</span>
        </template>
    </Table>

    <div class="space-y-4 p-4 text-sm text-gray-700">
        <div>
            <h2 class="text-base font-semibold text-gray-900">{{ ctrans("Where messages are written") }}</h2>
            <p class="text-xs text-gray-500">{{ ctrans("Messages people wrote in the public and private Slack channels kept in aiku, from aiku or in Slack. DMs, bots, system lines and deleted messages are left out; thread replies count. The table lists public channels only.") }}</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="text-xs text-gray-500">{{ ctrans("Written from aiku, last 7 days") }}</div>
                <div class="mt-1 flex items-baseline gap-2">
                    <span class="text-3xl font-semibold tabular-nums text-gray-900">{{ shareText(writing.share) }}</span>
                    <span v-if="shareChange !== null" v-tooltip="ctrans('Against the 7 days before: :share', { share: shareText(writing.previous_share) })" class="whitespace-nowrap text-xs font-medium tabular-nums" :class="shareChange > 0 ? 'text-green-600' : shareChange < 0 ? 'text-red-500' : 'text-gray-400'">
                        {{ shareChange > 0 ? "+" : "" }}{{ shareChange.toLocaleString() }} {{ ctrans("points") }}
                    </span>
                </div>
                <div class="mt-1 text-xs tabular-nums text-gray-500">{{ ctrans(":from_aiku of :messages messages", { from_aiku: number(writing.from_aiku), messages: number(writing.messages) }) }}</div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="text-xs text-gray-500">{{ ctrans("People who connected their Slack") }}</div>
                <div class="mt-1 text-3xl font-semibold tabular-nums text-gray-900">{{ number(writing.people_connected) }}</div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="text-xs text-gray-500">{{ ctrans("People who wrote from aiku, last 7 days") }}</div>
                <div class="mt-1 text-3xl font-semibold tabular-nums text-gray-900">{{ number(writing.people_writing_from_aiku) }}</div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-800">{{ ctrans("Messages per week, last :weeks weeks", { weeks: writing.weeks.length }) }}</h3>
            <div class="mt-3 h-64">
                <Chart type="line" :data="writingChart" :options="writingChartOptions" class="h-full" />
            </div>
        </div>
    </div>

    <Table :resource="writing_channels" name="writing">
        <template #cell(name)="{ item }">
            <span class="whitespace-nowrap font-medium text-gray-800">#{{ item.name }}</span>
        </template>

        <template #cell(messages)="{ item }">
            <span class="tabular-nums">{{ number(item.messages) }}</span>
        </template>

        <template #cell(from_aiku)="{ item }">
            <span class="tabular-nums">{{ number(item.from_aiku) }}</span>
        </template>

        <template #cell(share)="{ item }">
            <span class="tabular-nums">{{ shareText(item.share) }}</span>
        </template>
    </Table>
</template>
