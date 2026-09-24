<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 16 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed } from "vue"
import { Head } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import Chart from "primevue/chart"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { PageHeadingTypes } from "@/types/PageHeading"
import Image from "@/Common/Components/Image.vue"
import TicketsCreatedInterval from "@/Components/Tickets/TicketsCreatedInterval.vue"
import ProcurementOverviewPill from "@/Components/DataDisplay/Dashboard/Widget/ProcurementOverviewPill.vue"
import DashboardWidgetBox from "@/Components/DataDisplay/Dashboard/Widget/DashboardWidgetBox.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTasks, faCheck, faInboxIn, faStopwatch, faHourglassHalf, faChartLine, faChartPie, faUsers, faBuilding } from "@fal"

library.add(faTasks, faCheck, faInboxIn, faStopwatch, faHourglassHalf, faChartLine, faChartPie, faUsers, faBuilding)

type Metrics = { created: number; open: number; done: number; cancelled: number; stale: number; median_hours: number | null; longest_wait_days: number | null }

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    createdIntervals: Record<string, string>
    listAllRoute: { name: string; parameters: Record<string, any> | any[] }
    stats: {
        interval: string
        bucket: "day" | "week" | "month"
        from: string
        open_now: number
        stale_now: number
        oldest_open: { reference: string; age_days: number } | null
        totals: Metrics
        series: { date: string; created: number; done: number; open: number }[]
        by_status: { status: string; label: string; color: string; total: number }[]
        by_department: (Metrics & { department: string; label: string })[]
        by_assignee: (Metrics & { name: string; avatar: any })[]
        by_requester: (Metrics & { name: string })[]
    }
}>()

const STATUS_COLORS: Record<string, string> = { todo: "#9ca3af", in_progress: "#3b82f6", done: "#16a34a", cancelled: "#f87171" }

const bucketLabel = (date: string) => {
    const parsed = new Date(`${date}T00:00:00`)
    return props.stats.bucket === "month"
        ? parsed.toLocaleDateString(undefined, { month: "short", year: "2-digit" })
        : parsed.toLocaleDateString(undefined, { day: "numeric", month: "short" })
}

const lineChart = computed(() => {
    const pointRadius = props.stats.series.length > 40 ? 0 : 2
    return {
        labels: props.stats.series.map((point) => bucketLabel(point.date)),
        datasets: [
            { label: ctrans("Raised"), data: props.stats.series.map((p) => p.created), borderColor: "#c0399f", backgroundColor: "#c0399f", tension: 0, borderWidth: 1.5, pointRadius },
            { label: ctrans("Done"), data: props.stats.series.map((p) => p.done), borderColor: "#1f845a", backgroundColor: "#1f845a", tension: 0, borderWidth: 1.5, pointRadius },
            { label: ctrans("Open"), data: props.stats.series.map((p) => p.open), borderColor: "#f59e0b", backgroundColor: "#f59e0b", tension: 0, borderWidth: 1.5, pointRadius },
        ],
    }
})

const lineOptions = { responsive: true, maintainAspectRatio: false, interaction: { mode: "index", intersect: false }, plugins: { legend: { position: "bottom", labels: { boxWidth: 12 } } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }

const statusChart = computed(() => ({
    labels: props.stats.by_status.map((s) => s.label),
    datasets: [{ data: props.stats.by_status.map((s) => s.total), backgroundColor: props.stats.by_status.map((s) => STATUS_COLORS[s.status]) }],
}))

const hours = (value: number | null) => (value === null ? "—" : value < 48 ? `${value} h` : `${Math.round(value / 24)} d`)
</script>

<template>
    <Head :title="title" />
    <PageHeading :data="pageHead" />
    <div class="p-4 space-y-4">
        <div class="flex flex-wrap gap-3 items-center">
            <ProcurementOverviewPill :card="{ label: ctrans('Open now'), description: '', icon: 'fal fa-inbox-in', value: stats.open_now, tone: 'amber', metrics: [], route: listAllRoute }" />
            <ProcurementOverviewPill :card="{ label: ctrans('Older than 7 days'), description: '', icon: 'fal fa-hourglass-half', value: stats.stale_now, tone: stats.stale_now ? 'amber' : 'emerald', metrics: [], route: listAllRoute }" />
            <span v-if="stats.oldest_open" v-tooltip="ctrans('Oldest open')" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm tabular-nums">
                <FontAwesomeIcon icon="fal fa-hourglass-half" class="text-red-500" fixed-width aria-hidden="true" />{{ stats.oldest_open.age_days }} {{ ctrans('days') }}
                <span class="border-l border-gray-200 pl-2 font-normal text-gray-500">{{ stats.oldest_open.reference }}</span>
            </span>
            <TicketsCreatedInterval :options="createdIntervals" :selected="stats.interval" class="min-w-0 flex-1" />
        </div>

        <div class="flex flex-wrap gap-3">
            <ProcurementOverviewPill :card="{ label: ctrans('Raised'), description: '', icon: 'fal fa-tasks', value: stats.totals.created, tone: 'violet', metrics: [], route: listAllRoute }" />
            <ProcurementOverviewPill :card="{ label: ctrans('Done'), description: '', icon: 'fal fa-check', value: stats.totals.done, tone: 'emerald', metrics: [], route: listAllRoute }" />
            <ProcurementOverviewPill :card="{ label: ctrans(`Can't be done`), description: '', icon: 'fal fa-ban', value: stats.totals.cancelled, tone: 'indigo', metrics: [], route: listAllRoute }" />
            <ProcurementOverviewPill :card="{ label: ctrans('Median hours to done'), description: '', icon: 'fal fa-stopwatch', value: stats.totals.median_hours ?? 0, tone: 'sky', metrics: [], route: listAllRoute }" />
        </div>

        <DashboardWidgetBox storageKey="tasks_reports_raised_vs_done_collapsed">
            <template #header>
                <span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
                    <FontAwesomeIcon icon="fal fa-chart-line" class="text-pink-600" fixed-width aria-hidden="true" />
                    {{ ctrans('Raised vs done') }}
                </span>
                <span class="text-xs text-gray-400">{{ stats.totals.created }} {{ ctrans('raised') }} · {{ stats.totals.done }} {{ ctrans('done') }}</span>
            </template>
            <div class="grid gap-6 lg:grid-cols-5">
                <div class="h-72 lg:col-span-3"><Chart type="line" :data="lineChart" :options="lineOptions" class="h-full" /></div>
                <div class="h-72 lg:col-span-2"><Chart type="doughnut" :data="statusChart" :options="{ responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } } }" class="h-full" /></div>
            </div>
        </DashboardWidgetBox>

        <DashboardWidgetBox v-for="table in [
            { key: 'department', icon: 'fal fa-building', title: ctrans('By department'), rows: stats.by_department.map((r) => ({ ...r, name: r.label })) },
            { key: 'assignee', icon: 'fal fa-users', title: ctrans('By person, shared between collaborators'), rows: stats.by_assignee },
            { key: 'requester', icon: 'fal fa-user', title: ctrans('By requester'), rows: stats.by_requester },
        ]" :key="table.key" :storageKey="`tasks_reports_${table.key}_collapsed`">
            <template #header>
                <span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
                    <FontAwesomeIcon :icon="table.icon" class="text-[--app-accent]" fixed-width aria-hidden="true" />
                    {{ table.title }}
                </span>
            </template>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-xs text-gray-500">
                        <tr class="text-right">
                            <th class="text-left py-1.5 pr-3 font-medium">{{ ctrans('Name') }}</th>
                            <th class="py-1.5 px-2 font-medium">{{ ctrans('Raised') }}</th>
                            <th class="py-1.5 px-2 font-medium">{{ ctrans('Open') }}</th>
                            <th class="py-1.5 px-2 font-medium">{{ ctrans('Done') }}</th>
                            <th class="py-1.5 px-2 font-medium">{{ ctrans(`Can't`) }}</th>
                            <th class="py-1.5 px-2 font-medium">{{ ctrans('> 7 days') }}</th>
                            <th class="py-1.5 px-2 font-medium">{{ ctrans('Median') }}</th>
                            <th class="py-1.5 pl-2 font-medium">{{ ctrans('Longest wait') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 tabular-nums">
                        <tr v-for="row in table.rows" :key="row.name" class="text-right">
                            <td class="text-left py-1.5 pr-3 text-gray-800">
                                <span class="flex items-center gap-x-2">
                                    <span v-if="'avatar' in row" class="h-6 w-6 rounded-full overflow-hidden bg-gray-200 shrink-0">
                                        <Image v-if="row.avatar" :src="row.avatar" :alt="row.name" image-cover />
                                    </span>
                                    {{ row.name }}
                                </span>
                            </td>
                            <td class="py-1.5 px-2">{{ row.created }}</td>
                            <td class="py-1.5 px-2" :class="row.open ? 'text-amber-600' : 'text-gray-400'">{{ row.open }}</td>
                            <td class="py-1.5 px-2 text-green-700">{{ row.done }}</td>
                            <td class="py-1.5 px-2" :class="row.cancelled ? 'text-red-600' : 'text-gray-400'">{{ row.cancelled }}</td>
                            <td class="py-1.5 px-2" :class="row.stale ? 'text-red-600 font-semibold' : 'text-gray-400'">{{ row.stale }}</td>
                            <td class="py-1.5 px-2">{{ hours(row.median_hours) }}</td>
                            <td class="py-1.5 pl-2">{{ row.longest_wait_days === null ? '—' : row.longest_wait_days + ' d' }}</td>
                        </tr>
                        <tr v-if="!table.rows.length"><td colspan="8" class="py-4 text-center text-xs text-gray-400">{{ ctrans('Nothing in this period') }}</td></tr>
                    </tbody>
                </table>
            </div>
        </DashboardWidgetBox>
    </div>
</template>
