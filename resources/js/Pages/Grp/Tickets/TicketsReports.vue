<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3"
import { computed, ref } from "vue"
import { trans } from "laravel-vue-i18n"
import { capitalize } from "@/Composables/capitalize"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Chart from "primevue/chart"
import TicketsCreatedInterval from "@/Components/Tickets/TicketsCreatedInterval.vue"
import { useLiveTickets } from "@/Composables/useLiveTickets"
import ProcurementOverviewPill from "@/Components/DataDisplay/Dashboard/Widget/ProcurementOverviewPill.vue"
import DashboardWidgetBox from "@/Components/DataDisplay/Dashboard/Widget/DashboardWidgetBox.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTicketAlt, faCheck, faInboxIn, faStopwatch, faStar, faHourglassHalf, faChartLine, faChartPie, faUsers } from "@fal"

library.add(faTicketAlt, faCheck, faInboxIn, faStopwatch, faStar, faHourglassHalf, faChartLine, faChartPie, faUsers)

const props = defineProps<{
    pageHead: any
    title: string
    createdIntervals: Record<string, string>
    stats: {
        interval: string
        days: number
        bucket: "day" | "week" | "month"
        from: string
        created: number
        done: number
        open: number
        median_hours: number | null
        oldest_open: { reference: string; age_days: number } | null
        csat: number | null
        csat_by_month: { month: string; average: number | null; total: number }[]
        daily: { date: string; created: number; done: number }[]
        by_status: { status: string; label: string; color: string; total: number }[]
        assignees: (Metrics & { name: string; username: string; short_name: string; avatar: any })[]
        assignees_total: Metrics
        resolvers: (Metrics & { name: string; username: string; short_name: string; avatar: any })[]
        resolvers_total: Metrics
        reporters: (Metrics & { key: string; name: string; is_staff: boolean })[]
    }
}>()

type Metrics = { created: number; open: number; assigned: number; in_progress: number; resolved: number; cancelled: number; done: number; median_hours: number | null; longest_wait_days: number | null; rating: number | null; ratings: number }

const peopleTab = ref<"assignees" | "reporters">("assignees")

useLiveTickets(["stats"])

const STATUS_COLORS: Record<string, string> = {
    open: "#9ca3af",
    assigned: "#7c8fb5",
    in_progress: "#3b82f6",
    waiting: "#93c5fd",
    answered: "#f59e0b",
    pending_deploy: "#86efac",
    resolved: "#16a34a",
    cancelled: "#d1d5db",
}

const bucketLabel = (date: string) => {
    const parsed = new Date(`${date}T00:00:00`)
    if (props.stats.bucket === "month") {
        return parsed.toLocaleDateString(undefined, { month: "short", year: "2-digit" })
    }
    return parsed.toLocaleDateString(undefined, { day: "numeric", month: "short" })
}

const lineChart = computed(() => {
    const pointRadius = props.stats.daily.length > 40 ? 0 : 2
    return {
        labels: props.stats.daily.map((day) => bucketLabel(day.date)),
        datasets: [
            { label: trans("Created"), data: props.stats.daily.map((day) => day.created), borderColor: "#c0399f", backgroundColor: "#c0399f", tension: 0, borderWidth: 1.5, pointRadius },
            { label: trans("Resolved"), data: props.stats.daily.map((day) => day.done), borderColor: "#1f845a", backgroundColor: "#1f845a", tension: 0, borderWidth: 1.5, pointRadius },
        ],
    }
})

const lineOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: "index", intersect: false },
    plugins: {
        legend: { position: "bottom", labels: { boxWidth: 12 } },
        tooltip: { callbacks: { title: (items: any[]) => (props.stats.bucket === "day" ? items[0].label : `${trans(props.stats.bucket === "week" ? "Week of" : "Month")} ${items[0].label}`) } },
    },
    scales: {
        x: { grid: { display: false }, ticks: { autoSkip: true, maxTicksLimit: 12, maxRotation: 0 } },
        y: { beginAtZero: true, ticks: { precision: 0 } },
    },
}))

const donutChart = computed(() => ({
    labels: props.stats.by_status.map((row) => row.label),
    datasets: [{ data: props.stats.by_status.map((row) => row.total), backgroundColor: props.stats.by_status.map((row) => STATUS_COLORS[row.status] ?? "#9ca3af") }],
}))

const donutOptions = { responsive: true, maintainAspectRatio: false, cutout: "70%", plugins: { legend: { display: false } } }

const csatChart = computed(() => ({
    labels: props.stats.csat_by_month.map((row) => row.month.slice(2)),
    datasets: [{ label: trans("Average rating"), data: props.stats.csat_by_month.map((row) => row.average), backgroundColor: "#3b82f6", borderRadius: 4 }],
}))

const csatOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: { x: { grid: { display: false } }, y: { beginAtZero: true, max: 5, ticks: { stepSize: 1 } } },
}

const totalTickets = computed(() => props.stats.by_status.reduce((sum, row) => sum + row.total, 0))

const hours = (value: number | null) => (value === null ? "-" : value >= 48 ? `${(value / 24).toFixed(1)} ${trans("days")}` : `${value} ${trans("h")}`)

const OPEN_STATUSES = computed(() => props.stats.by_status.filter((s) => !["resolved", "cancelled"].includes(s.status)).map((s) => s.status).join(","))

const listUrl = (params: Record<string, any>) => route("grp.tickets.list", params)

const listRoute = (params: Record<string, any>) => ({ name: "grp.tickets.list", parameters: params })

const pillValue = (value: string | number | null) => (value === null ? "-" : value)

type TableMode = "assignees" | "resolvers" | "reporters"

const sortStates = ref<Record<TableMode, { key: string; direction: 1 | -1 }>>({
    assignees: { key: "", direction: -1 },
    resolvers: { key: "", direction: -1 },
    reporters: { key: "", direction: -1 },
})

const sortArrow = (table: TableMode, key: string) => (sortStates.value[table].key === key ? (sortStates.value[table].direction === 1 ? " ▲" : " ▼") : "")

const toggleSort = (table: TableMode, key: string) => {
    const current = sortStates.value[table]
    sortStates.value[table] = current.key === key ? { key, direction: current.direction === 1 ? -1 : 1 } : { key, direction: ["name", "short_name"].includes(key) ? 1 : -1 }
}

const sortRows = <T extends Record<string, any>>(rows: T[], table: TableMode): T[] => {
    const { key, direction } = sortStates.value[table]
    if (!key) {
        return rows
    }
    return [...rows].sort((a, b) => {
        if (a[key] === null || a[key] === undefined) {
            return 1
        }
        if (b[key] === null || b[key] === undefined) {
            return -1
        }
        return (typeof a[key] === "string" ? a[key].localeCompare(b[key]) : a[key] - b[key]) * direction
    })
}

const sortedReporters = computed(() => sortRows(props.stats.reporters, "reporters"))

const engineerRows = (mode: "assignees" | "resolvers") => sortRows(mode === "resolvers" ? props.stats.resolvers : props.stats.assignees, mode)

const engineerTotal = (mode: "assignees" | "resolvers") => (mode === "resolvers" ? props.stats.resolvers_total : props.stats.assignees_total)

const sharePercent = (value: number, total: number) => (total && value ? `${((value / total) * 100).toFixed(1)}%` : "")

const days = (value: number | null) => (value === null ? "-" : `${value} ${trans("days")}`)

const peopleTabs = [
    { key: "assignees", label: trans("Engineers") },
    { key: "reporters", label: trans("Reporters") },
]

const reporterColumns = [
    { key: "name", label: trans("Reporter") },
    { key: "created", label: trans("Created") },
    { key: "open", label: trans("Still open") },
    { key: "resolved", label: trans("Resolved") },
    { key: "cancelled", label: trans("Cancelled") },
    { key: "median_hours", label: trans("Median time to resolve") },
    { key: "longest_wait_days", label: trans("Longest wait") },
    { key: "rating", label: trans("Average rating") },
]

const assigneeColumns = [
    { key: "short_name", label: trans("Engineer") },
    { key: "assigned", label: trans("To do") },
    { key: "in_progress", label: trans("Working on") },
    { key: "open", label: trans("Still open") },
    { key: "done", label: trans("Resolved") },
    { key: "median_hours", label: trans("Median time to resolve") },
    { key: "longest_wait_days", label: trans("Longest wait") },
    { key: "rating", label: trans("Average rating") },
]

const engineerColumns = (mode: "assignees" | "resolvers") =>
    mode === "resolvers" ? assigneeColumns.filter((column) => !["assigned", "in_progress", "open", "longest_wait_days"].includes(column.key)) : assigneeColumns

const assigneeFilter = (mode: "assignees" | "resolvers", username: string) =>
    mode === "resolvers" ? { assignee: username, resolved_since: props.stats.from } : { assignee: username, created_since: props.stats.from }

const dashboardBoxes = computed(() => (props.stats.interval === "all" ? (["people"] as const) : (["people", "cleared"] as const)))
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <div class="p-4 space-y-4">
        <div class="flex flex-wrap gap-3">
            <ProcurementOverviewPill :card="{ label: trans('Open now'), description: '', icon: 'fal fa-inbox-in', value: stats.open, tone: 'amber', route: listRoute({ elements: { status: OPEN_STATUSES } }), metrics: [] }" />
            <Link v-if="stats.oldest_open" v-tooltip="trans('Oldest open')" :href="route('grp.tickets.show', stats.oldest_open.reference)" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm tabular-nums">
                <FontAwesomeIcon icon="fal fa-hourglass-half" class="text-red-500" fixed-width aria-hidden="true" />{{ stats.oldest_open.age_days }} {{ trans("days") }}
                <span class="border-l border-gray-200 pl-2 font-normal text-gray-500">{{ stats.oldest_open.reference }}</span>
            </Link>
        </div>

        <div class="space-y-4 rounded-xl border border-gray-200 bg-gray-50 p-3">
        <TicketsCreatedInterval :options="createdIntervals" :selected="stats.interval" />

        <div class="flex flex-wrap gap-3">
            <ProcurementOverviewPill :card="{ label: trans('Created'), description: '', icon: 'fal fa-ticket-alt', value: stats.created, tone: 'violet', route: listRoute({ filter: { created_since: stats.from } }), metrics: [] }" />
            <ProcurementOverviewPill :card="{ label: trans('Resolved'), description: '', icon: 'fal fa-check', value: stats.done, tone: 'emerald', route: listRoute({ filter: { resolved_since: stats.from } }), metrics: [] }" />
            <Link v-tooltip="trans('Median time to resolve')" :href="listUrl({ filter: { resolved_since: stats.from } })" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm tabular-nums">
                <FontAwesomeIcon icon="fal fa-stopwatch" class="text-indigo-600" fixed-width aria-hidden="true" />{{ hours(stats.median_hours) }}
            </Link>
            <Link v-tooltip="trans('Customer satisfaction')" :href="listUrl({ filter: { rated_since: stats.from } })" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm tabular-nums">
                <FontAwesomeIcon icon="fal fa-star" class="text-sky-600" fixed-width aria-hidden="true" />{{ pillValue(stats.csat) }}<span class="font-normal text-gray-400">/5</span>
            </Link>
        </div>

        <DashboardWidgetBox storageKey="tickets_reports_created_vs_done_collapsed">
            <template #header>
                <span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
                    <FontAwesomeIcon icon="fal fa-chart-line" class="text-pink-600" fixed-width aria-hidden="true" />
                    {{ trans("Created vs Resolved") }}
                </span>
                <span class="text-xs text-gray-400">{{ stats.created }} {{ trans("created") }} · {{ stats.done }} {{ trans("resolved") }}</span>
            </template>
            <div class="grid gap-6 lg:grid-cols-5">
                <div class="h-72 lg:col-span-3">
                    <Chart type="line" :data="lineChart" :options="lineOptions" class="h-full" />
                </div>
                <div class="lg:col-span-2 lg:border-l lg:border-gray-100 lg:pl-6">
                    <p class="mb-2 flex items-center gap-2 text-sm font-semibold text-gray-600">
                        <FontAwesomeIcon icon="fal fa-chart-pie" class="text-blue-600" fixed-width aria-hidden="true" />
                        {{ trans("Status overview") }}
                        <span class="text-xs font-normal text-gray-400">{{ trans("Tickets created in this period") }} · <Link :href="listUrl({ filter: { created_since: stats.from } })" class="hover:text-gray-600">{{ trans("View all") }}</Link></span>
                    </p>
                    <div class="flex flex-wrap items-center gap-6">
                        <div class="relative h-56 w-56 shrink-0">
                            <Chart type="doughnut" :data="donutChart" :options="donutOptions" class="h-full" />
                            <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                                <span class="text-3xl font-bold">{{ totalTickets }}</span>
                                <span class="text-xs text-gray-500">{{ trans("Total") }}</span>
                            </div>
                        </div>
                        <table class="text-base tabular-nums">
                            <tbody>
                                <tr v-for="row in stats.by_status.filter((status) => status.total)" :key="row.status">
                                    <td class="py-1 pr-5">
                                        <span class="flex items-center gap-2">
                                            <span class="h-3 w-3 shrink-0 rounded-sm" :style="{ backgroundColor: STATUS_COLORS[row.status] ?? '#9ca3af' }" />
                                            {{ row.label }}
                                        </span>
                                    </td>
                                    <td class="py-1 pr-5 text-right font-medium">
                                        <Link :href="listUrl({ filter: { created_since: stats.from }, elements: { status: row.status } })" class="hover:underline">{{ row.total }}</Link>
                                    </td>
                                    <td class="py-1 text-right text-gray-500">{{ totalTickets ? ((row.total / totalTickets) * 100).toFixed(1) : 0 }}%</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </DashboardWidgetBox>

        <DashboardWidgetBox v-for="box in dashboardBoxes" :key="box" :storageKey="`tickets_reports_${box}_collapsed`">
            <template #header>
                <template v-if="box === 'people'">
                    <span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
                        <FontAwesomeIcon icon="fal fa-users" class="text-violet-600" fixed-width aria-hidden="true" />
                        {{ trans("People") }}
                    </span>
                    <span class="flex items-center gap-1.5">
                        <button
                            v-for="tab in peopleTabs"
                            :key="tab.key"
                            type="button"
                            class="rounded-full border px-2.5 py-px text-xs"
                            :class="peopleTab === tab.key ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-200 text-gray-500 hover:bg-gray-50'"
                            @click="peopleTab = tab.key as 'assignees' | 'reporters'">
                            {{ tab.label }}
                        </button>
                    </span>
                    <span class="text-xs text-gray-400">{{ trans("Tickets created in this period") }}</span>
                </template>
                <template v-else>
                    <span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
                        <FontAwesomeIcon icon="fal fa-check" class="text-emerald-600" fixed-width aria-hidden="true" />
                        {{ trans("Cleared") }}
                    </span>
                    <span class="text-xs text-gray-400">{{ trans("Older tickets, created before this period, resolved in it") }}</span>
                </template>
            </template>

        <div v-if="box === 'people' && peopleTab === 'reporters'" class="-mx-4 -mb-4 overflow-x-auto">
            <table class="min-w-full text-sm tabular-nums">
                <thead class="text-xs text-gray-500 text-left">
                    <tr>
                        <th v-for="(column, index) in reporterColumns" :key="column.key" class="px-4 py-2 cursor-pointer select-none hover:text-gray-700" :class="{ 'text-right': index > 0 }" @click="toggleSort('reporters', column.key)">
                            {{ column.label }}{{ sortArrow("reporters", column.key) }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in sortedReporters" :key="row.key" class="border-t border-gray-100">
                        <td class="px-4 py-2 font-medium">
                            {{ row.name ?? "-" }}
                            <span v-if="!row.is_staff" class="ml-1 text-xs text-gray-400">{{ trans("Customer") }}</span>
                        </td>
                        <td class="px-4 py-2 text-right">{{ row.created }}<span class="inline-block w-16 text-gray-400">{{ sharePercent(row.created, stats.assignees_total.created) }}</span></td>
                        <td class="px-4 py-2 text-right">{{ row.open }}</td>
                        <td class="px-4 py-2 text-right">{{ row.resolved }}</td>
                        <td class="px-4 py-2 text-right">{{ row.cancelled }}<span class="inline-block w-16 text-gray-400">{{ sharePercent(row.cancelled, row.created) }}</span></td>
                        <td class="px-4 py-2 text-right">{{ hours(row.median_hours) }}</td>
                        <td class="px-4 py-2 text-right">{{ days(row.longest_wait_days) }}</td>
                        <td class="px-4 py-2 text-right">
                            <template v-if="row.rating !== null">{{ row.rating }}<span class="text-gray-400">/5 ({{ row.ratings }})</span></template>
                            <span v-else>-</span>
                        </td>
                    </tr>
                    <tr v-if="!stats.reporters.length">
                        <td colspan="8" class="px-4 py-6 text-center text-gray-400">{{ trans("No tickets in this period") }}</td>
                    </tr>
                </tbody>
                <tfoot v-if="stats.reporters.length" class="border-t-2 border-gray-200 font-semibold">
                    <tr>
                        <td class="px-4 py-2">{{ trans("Total") }}</td>
                        <td class="px-4 py-2 text-right">{{ stats.assignees_total.created }}<span class="inline-block w-16" /></td>
                        <td class="px-4 py-2 text-right">{{ stats.assignees_total.open }}</td>
                        <td class="px-4 py-2 text-right">{{ stats.assignees_total.resolved }}</td>
                        <td class="px-4 py-2 text-right">{{ stats.assignees_total.cancelled }}<span class="inline-block w-16 text-gray-400">{{ sharePercent(stats.assignees_total.cancelled, stats.assignees_total.created) }}</span></td>
                        <td class="px-4 py-2 text-right">{{ hours(stats.assignees_total.median_hours) }}</td>
                        <td class="px-4 py-2 text-right">{{ days(stats.assignees_total.longest_wait_days) }}</td>
                        <td class="px-4 py-2 text-right">
                            <template v-if="stats.assignees_total.rating !== null">{{ stats.assignees_total.rating }}<span class="text-gray-400">/5 ({{ stats.assignees_total.ratings }})</span></template>
                            <span v-else>-</span>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div v-else v-for="mode in [box === 'cleared' ? 'resolvers' : 'assignees'] as const" :key="mode" class="-mx-4 -mb-4 overflow-x-auto">
            <table class="min-w-full text-sm tabular-nums">
                <thead class="text-xs text-gray-500 text-left">
                    <tr>
                        <th v-for="(column, index) in engineerColumns(mode)" :key="column.key" class="px-4 py-2 cursor-pointer select-none hover:text-gray-700" :class="{ 'text-right': index > 0 }" @click="toggleSort(mode, column.key)">
                            {{ column.label }}{{ sortArrow(mode, column.key) }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in engineerRows(mode)" :key="row.username" class="border-t border-gray-100">
                        <td class="px-4 py-2 font-medium">
                            <span class="inline-flex items-center gap-2" v-tooltip="{ content: row.name, delay: 0 }">
                                <img v-if="row.avatar?.original" :src="row.avatar.original" class="h-6 w-6 rounded-full object-cover" />
                                <span v-else class="h-6 w-6 rounded-full bg-gray-300 inline-block" />
                                {{ row.short_name }}
                            </span>
                        </td>
                        <template v-if="mode === 'assignees'">
                            <td v-for="status in ['assigned', 'in_progress'] as const" :key="status" class="px-4 py-2 text-right">
                                <Link v-if="row[status]" :href="listUrl({ filter: assigneeFilter(mode, row.username), elements: { status } })" class="hover:underline">{{ row[status] }}</Link>
                                <span v-else>{{ row[status] }}</span>
                            </td>
                        </template>
                        <td v-if="mode === 'assignees'" class="px-4 py-2 text-right">
                            <Link v-if="row.open" :href="listUrl({ filter: assigneeFilter(mode, row.username), elements: { status: OPEN_STATUSES } })" class="hover:underline">{{ row.open }}</Link>
                            <span v-else>{{ row.open }}</span>
                        </td>
                        <td class="px-4 py-2 text-right">
                            <Link v-if="row.done" :href="listUrl({ filter: assigneeFilter(mode, row.username), elements: { status: 'resolved' } })" class="hover:underline">{{ row.done }}</Link>
                            <span v-else>{{ row.done }}</span>
                            <span class="inline-block w-16 text-gray-400">{{ sharePercent(row.done, engineerTotal(mode).done) }}</span>
                        </td>
                        <td class="px-4 py-2 text-right">{{ hours(row.median_hours) }}</td>
                        <td v-if="mode === 'assignees'" class="px-4 py-2 text-right">{{ days(row.longest_wait_days) }}</td>
                        <td class="px-4 py-2 text-right">
                            <template v-if="row.rating !== null">{{ row.rating }}<span class="text-gray-400">/5 ({{ row.ratings }})</span></template>
                            <span v-else>-</span>
                        </td>
                    </tr>
                    <tr v-if="!engineerRows(mode).length">
                        <td :colspan="engineerColumns(mode).length" class="px-4 py-6 text-center text-gray-400">{{ trans("No tickets in this period") }}</td>
                    </tr>
                </tbody>
                <tfoot v-if="engineerRows(mode).length" class="border-t-2 border-gray-200 font-semibold">
                    <tr>
                        <td class="px-4 py-2">{{ trans("Total") }}</td>
                        <td v-if="mode === 'assignees'" class="px-4 py-2 text-right">{{ engineerTotal(mode).assigned }}</td>
                        <td v-if="mode === 'assignees'" class="px-4 py-2 text-right">{{ engineerTotal(mode).in_progress }}</td>
                        <td v-if="mode === 'assignees'" class="px-4 py-2 text-right">{{ engineerTotal(mode).open }}</td>
                        <td class="px-4 py-2 text-right">{{ engineerTotal(mode).done }}<span class="inline-block w-16" /></td>
                        <td class="px-4 py-2 text-right">{{ hours(engineerTotal(mode).median_hours) }}</td>
                        <td v-if="mode === 'assignees'" class="px-4 py-2 text-right">{{ days(engineerTotal(mode).longest_wait_days) }}</td>
                        <td class="px-4 py-2 text-right">
                            <template v-if="engineerTotal(mode).rating !== null">{{ engineerTotal(mode).rating }}<span class="text-gray-400">/5 ({{ engineerTotal(mode).ratings }})</span></template>
                            <span v-else>-</span>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        </DashboardWidgetBox>
        </div>

        <DashboardWidgetBox storageKey="tickets_reports_csat_collapsed" default-collapsed>
            <template #header>
                <span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
                    <FontAwesomeIcon icon="fal fa-star" class="text-sky-600" fixed-width aria-hidden="true" />
                    {{ trans("Customer satisfaction") }}
                </span>
                <span class="text-xs text-gray-400">{{ trans("Average rating per month, last 12 months") }}</span>
            </template>
            <div class="h-56">
                <Chart type="bar" :data="csatChart" :options="csatOptions" class="h-full" />
            </div>
        </DashboardWidgetBox>
    </div>
</template>
