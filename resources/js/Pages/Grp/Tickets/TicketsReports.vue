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

type Metrics = { created: number; open: number; done: number; median_hours: number | null; longest_wait_days: number | null; rating: number | null; ratings: number }

const peopleTab = ref<"assignees" | "resolvers" | "reporters">("assignees")

useLiveTickets(["stats"])

const STATUS_COLORS: Record<string, string> = { blue: "#3b82f6", amber: "#f59e0b", gray: "#9ca3af", green: "#22c55e" }

const lineChart = computed(() => ({
    labels: props.stats.daily.map((day) => day.date.slice(5)),
    datasets: [
        { label: trans("Created"), data: props.stats.daily.map((day) => day.created), borderColor: "#c0399f", backgroundColor: "#c0399f", tension: 0.2 },
        { label: trans("Resolved"), data: props.stats.daily.map((day) => day.done), borderColor: "#1f845a", backgroundColor: "#1f845a", tension: 0.2 },
    ],
}))

const lineOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: "bottom", labels: { boxWidth: 12 } } },
    scales: { x: { grid: { display: false } }, y: { beginAtZero: true, ticks: { precision: 0 } } },
}

const donutChart = computed(() => ({
    labels: props.stats.by_status.map((row) => row.label),
    datasets: [{ data: props.stats.by_status.map((row) => row.total), backgroundColor: props.stats.by_status.map((row) => STATUS_COLORS[row.color] ?? "#9ca3af") }],
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

const sortState = ref<{ key: string; direction: 1 | -1 }>({ key: "", direction: -1 })

const toggleSort = (key: string) => {
    sortState.value = sortState.value.key === key ? { key, direction: sortState.value.direction === 1 ? -1 : 1 } : { key, direction: ["name", "short_name"].includes(key) ? 1 : -1 }
}

const sortRows = <T extends Record<string, any>>(rows: T[]): T[] => {
    const { key, direction } = sortState.value
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

const sortedReporters = computed(() => sortRows(props.stats.reporters))
const sortedAssignees = computed(() => sortRows(peopleTab.value === "resolvers" ? props.stats.resolvers : props.stats.assignees))
const assigneeTotal = computed(() => (peopleTab.value === "resolvers" ? props.stats.resolvers_total : props.stats.assignees_total))

const days = (value: number | null) => (value === null ? "-" : `${value} ${trans("days")}`)

const peopleTabs = [
    { key: "assignees", label: trans("Engineers") },
    { key: "resolvers", label: trans("Resolved") },
    { key: "reporters", label: trans("Reporters") },
]

const reporterColumns = [
    { key: "name", label: trans("Reporter") },
    { key: "created", label: trans("Created") },
    { key: "open", label: trans("Still open") },
    { key: "done", label: trans("Resolved") },
    { key: "median_hours", label: trans("Median time to resolve") },
    { key: "longest_wait_days", label: trans("Longest wait") },
    { key: "rating", label: trans("Average rating") },
]

const assigneeColumns = [
    { key: "short_name", label: trans("Engineer") },
    { key: "created", label: trans("Landed") },
    { key: "open", label: trans("Still open") },
    { key: "done", label: trans("Resolved") },
    { key: "median_hours", label: trans("Median time to resolve") },
    { key: "longest_wait_days", label: trans("Longest wait") },
    { key: "rating", label: trans("Average rating") },
]

const isResolvedTab = computed(() => peopleTab.value === "resolvers")

const visibleAssigneeColumns = computed(() =>
    isResolvedTab.value ? assigneeColumns.filter((column) => !["created", "open", "longest_wait_days"].includes(column.key)) : assigneeColumns
)

const assigneeFilter = (username: string) =>
    peopleTab.value === "resolvers" ? { assignee: username, resolved_since: props.stats.from } : { assignee: username, created_since: props.stats.from }
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <div class="p-4 space-y-4">
        <TicketsCreatedInterval :options="createdIntervals" :selected="stats.interval" />

        <div class="flex flex-wrap gap-3">
            <ProcurementOverviewPill :card="{ label: trans('Created'), description: '', icon: 'fal fa-ticket-alt', value: stats.created, tone: 'violet', route: listRoute({ filter: { created_since: stats.from } }), metrics: [] }" />
            <ProcurementOverviewPill :card="{ label: trans('Resolved'), description: '', icon: 'fal fa-check', value: stats.done, tone: 'emerald', route: listRoute({ filter: { resolved_since: stats.from } }), metrics: [] }" />
            <ProcurementOverviewPill :card="{ label: trans('Open now'), description: '', icon: 'fal fa-inbox-in', value: stats.open, tone: 'amber', route: listRoute({ elements: { status: OPEN_STATUSES } }), metrics: [] }" />
            <Link v-tooltip="trans('Median time to resolve')" :href="listUrl({ filter: { resolved_since: stats.from } })" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm tabular-nums">
                <FontAwesomeIcon icon="fal fa-stopwatch" class="text-indigo-600" fixed-width aria-hidden="true" />{{ hours(stats.median_hours) }}
            </Link>
            <Link v-tooltip="trans('Customer satisfaction')" :href="listUrl({ filter: { rated_since: stats.from } })" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm tabular-nums">
                <FontAwesomeIcon icon="fal fa-star" class="text-sky-600" fixed-width aria-hidden="true" />{{ pillValue(stats.csat) }}<span class="font-normal text-gray-400">/5</span>
            </Link>
            <Link v-if="stats.oldest_open" v-tooltip="trans('Oldest open')" :href="route('grp.tickets.show', stats.oldest_open.reference)" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm tabular-nums">
                <FontAwesomeIcon icon="fal fa-hourglass-half" class="text-red-500" fixed-width aria-hidden="true" />{{ stats.oldest_open.age_days }} {{ trans("days") }}
                <span class="border-l border-gray-200 pl-2 font-normal text-gray-500">{{ stats.oldest_open.reference }}</span>
            </Link>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <DashboardWidgetBox storageKey="tickets_reports_created_vs_done_collapsed" class="lg:col-span-2 self-start">
                <template #header>
                    <span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
                        <FontAwesomeIcon icon="fal fa-chart-line" class="text-pink-600" fixed-width aria-hidden="true" />
                        {{ trans("Created vs Resolved") }}
                    </span>
                    <span class="text-xs text-gray-400">{{ stats.created }} {{ trans("created") }} · {{ stats.done }} {{ trans("resolved") }}</span>
                </template>
                <div class="h-72">
                    <Chart type="line" :data="lineChart" :options="lineOptions" class="h-full" />
                </div>
            </DashboardWidgetBox>
            <DashboardWidgetBox storageKey="tickets_reports_status_collapsed" class="self-start">
                <template #header>
                    <span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
                        <FontAwesomeIcon icon="fal fa-chart-pie" class="text-blue-600" fixed-width aria-hidden="true" />
                        {{ trans("Status overview") }}
                    </span>
                    <span class="text-xs text-gray-400">{{ trans("All tickets") }} · <Link :href="route('grp.tickets.list')" class="hover:text-gray-600">{{ trans("View all") }}</Link></span>
                </template>
                <div class="flex items-center gap-4">
                    <div class="relative h-40 w-40 shrink-0">
                        <Chart type="doughnut" :data="donutChart" :options="donutOptions" class="h-full" />
                        <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                            <span class="text-2xl font-bold">{{ totalTickets }}</span>
                            <span class="text-[10px] text-gray-500">{{ trans("Total") }}</span>
                        </div>
                    </div>
                    <ul class="space-y-1.5 text-sm">
                        <li v-for="row in stats.by_status" :key="row.status" class="flex items-center gap-2">
                            <span class="h-3 w-3 rounded-sm" :style="{ backgroundColor: STATUS_COLORS[row.color] ?? '#9ca3af' }" />
                            {{ row.label }}:
                            <Link v-if="row.total" :href="listUrl({ elements: { status: row.status } })" class="hover:underline font-medium">{{ row.total }}</Link>
                            <span v-else class="font-medium">{{ row.total }}</span>
                        </li>
                    </ul>
                </div>
            </DashboardWidgetBox>
        </div>

        <DashboardWidgetBox storageKey="tickets_reports_csat_collapsed">
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

        <DashboardWidgetBox storageKey="tickets_reports_people_collapsed">
            <template #header>
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
                        @click="peopleTab = tab.key as 'assignees' | 'resolvers' | 'reporters'">
                        {{ tab.label }}
                    </button>
                </span>
                <span class="text-xs text-gray-400">
                    {{ peopleTab === "resolvers" ? trans("Tickets resolved in this period, created at any time") : trans("Tickets created in this period") }}
                </span>
            </template>

        <div v-if="peopleTab === 'reporters'" class="-mx-4 -mb-4 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-xs text-gray-500 text-left">
                    <tr>
                        <th v-for="(column, index) in reporterColumns" :key="column.key" class="px-4 py-2 cursor-pointer select-none hover:text-gray-700" :class="{ 'text-right': index > 0 }" @click="toggleSort(column.key)">
                            {{ column.label }}<span v-if="sortState.key === column.key">{{ sortState.direction === 1 ? " ▲" : " ▼" }}</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in sortedReporters" :key="row.key" class="border-t border-gray-100">
                        <td class="px-4 py-2 font-medium">
                            {{ row.name ?? "-" }}
                            <span v-if="!row.is_staff" class="ml-1 text-xs text-gray-400">{{ trans("Customer") }}</span>
                        </td>
                        <td class="px-4 py-2 text-right">{{ row.created }}</td>
                        <td class="px-4 py-2 text-right">{{ row.open }}</td>
                        <td class="px-4 py-2 text-right">{{ row.done }}</td>
                        <td class="px-4 py-2 text-right">{{ hours(row.median_hours) }}</td>
                        <td class="px-4 py-2 text-right">{{ days(row.longest_wait_days) }}</td>
                        <td class="px-4 py-2 text-right">
                            <template v-if="row.rating !== null">{{ row.rating }}<span class="text-gray-400">/5 ({{ row.ratings }})</span></template>
                            <span v-else>-</span>
                        </td>
                    </tr>
                    <tr v-if="!stats.reporters.length">
                        <td colspan="7" class="px-4 py-6 text-center text-gray-400">{{ trans("No tickets in this period") }}</td>
                    </tr>
                </tbody>
                <tfoot v-if="stats.reporters.length" class="border-t-2 border-gray-200 font-semibold">
                    <tr>
                        <td class="px-4 py-2">{{ trans("Total") }}</td>
                        <td class="px-4 py-2 text-right">{{ stats.assignees_total.created }}</td>
                        <td class="px-4 py-2 text-right">{{ stats.assignees_total.open }}</td>
                        <td class="px-4 py-2 text-right">{{ stats.assignees_total.done }}</td>
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

        <div v-else class="-mx-4 -mb-4 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-xs text-gray-500 text-left">
                    <tr>
                        <th v-for="(column, index) in visibleAssigneeColumns" :key="column.key" class="px-4 py-2 cursor-pointer select-none hover:text-gray-700" :class="{ 'text-right': index > 0 }" @click="toggleSort(column.key)">
                            {{ column.label }}<span v-if="sortState.key === column.key">{{ sortState.direction === 1 ? " ▲" : " ▼" }}</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in sortedAssignees" :key="row.username" class="border-t border-gray-100">
                        <td class="px-4 py-2 font-medium">
                            <span class="inline-flex items-center gap-2" v-tooltip="{ content: row.name, delay: 0 }">
                                <img v-if="row.avatar?.original" :src="row.avatar.original" class="h-6 w-6 rounded-full object-cover" />
                                <span v-else class="h-6 w-6 rounded-full bg-gray-300 inline-block" />
                                {{ row.short_name }}
                            </span>
                        </td>
                        <td v-if="!isResolvedTab" class="px-4 py-2 text-right">
                            <Link v-if="row.created" :href="listUrl({ filter: assigneeFilter(row.username) })" class="hover:underline">{{ row.created }}</Link>
                            <span v-else>{{ row.created }}</span>
                        </td>
                        <td v-if="!isResolvedTab" class="px-4 py-2 text-right">
                            <Link v-if="row.open" :href="listUrl({ filter: assigneeFilter(row.username), elements: { status: OPEN_STATUSES } })" class="hover:underline">{{ row.open }}</Link>
                            <span v-else>{{ row.open }}</span>
                        </td>
                        <td class="px-4 py-2 text-right">
                            <Link v-if="row.done" :href="listUrl({ filter: assigneeFilter(row.username), elements: { status: 'resolved' } })" class="hover:underline">{{ row.done }}</Link>
                            <span v-else>{{ row.done }}</span>
                        </td>
                        <td class="px-4 py-2 text-right">{{ hours(row.median_hours) }}</td>
                        <td v-if="!isResolvedTab" class="px-4 py-2 text-right">{{ days(row.longest_wait_days) }}</td>
                        <td class="px-4 py-2 text-right">
                            <template v-if="row.rating !== null">{{ row.rating }}<span class="text-gray-400">/5 ({{ row.ratings }})</span></template>
                            <span v-else>-</span>
                        </td>
                    </tr>
                    <tr v-if="!sortedAssignees.length">
                        <td :colspan="visibleAssigneeColumns.length" class="px-4 py-6 text-center text-gray-400">{{ trans("No tickets in this period") }}</td>
                    </tr>
                </tbody>
                <tfoot v-if="sortedAssignees.length" class="border-t-2 border-gray-200 font-semibold">
                    <tr>
                        <td class="px-4 py-2">{{ trans("Total") }}</td>
                        <td v-if="!isResolvedTab" class="px-4 py-2 text-right">{{ assigneeTotal.created }}</td>
                        <td v-if="!isResolvedTab" class="px-4 py-2 text-right">{{ assigneeTotal.open }}</td>
                        <td class="px-4 py-2 text-right">{{ assigneeTotal.done }}</td>
                        <td class="px-4 py-2 text-right">{{ hours(assigneeTotal.median_hours) }}</td>
                        <td v-if="!isResolvedTab" class="px-4 py-2 text-right">{{ days(assigneeTotal.longest_wait_days) }}</td>
                        <td class="px-4 py-2 text-right">
                            <template v-if="assigneeTotal.rating !== null">{{ assigneeTotal.rating }}<span class="text-gray-400">/5 ({{ assigneeTotal.ratings }})</span></template>
                            <span v-else>-</span>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        </DashboardWidgetBox>
    </div>
</template>
