<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 16 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { Head, Link, router } from "@inertiajs/vue3"
import Chart from "primevue/chart"
import { ctrans } from "@/Composables/useTrans"
import { capitalize } from "@/Composables/capitalize"
import { taskRoute, tasksRoute, tasksRouteObject } from "@/Composables/useTasksRoute"
import { useLiveStaffTasks } from "@/Composables/useLiveStaffTasks"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TicketUserAvatar from "@/Components/Tickets/TicketUserAvatar.vue"
import TicketsCreatedInterval from "@/Components/Tickets/TicketsCreatedInterval.vue"
import ProcurementOverviewPill from "@/Components/DataDisplay/Dashboard/Widget/ProcurementOverviewPill.vue"
import DashboardWidgetBox from "@/Components/DataDisplay/Dashboard/Widget/DashboardWidgetBox.vue"
import { PageHeadingTypes } from "@/types/PageHeading"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTasks, faCheck, faInboxIn, faStopwatch, faHourglassHalf, faChartLine, faChartPie, faUsers, faBuilding, faBan } from "@fal"

library.add(faTasks, faCheck, faInboxIn, faStopwatch, faHourglassHalf, faChartLine, faChartPie, faUsers, faBuilding, faBan)

type Metrics = { created: number; todo: number; in_progress: number; open: number; done: number; cancelled: number; stale: number; median_hours: number | null; longest_wait_days: number | null }
type PersonRow = Metrics & { id: number; name: string; short_name: string; avatar: any }
type DepartmentRow = Metrics & { department: string; name: string }
type Role = "assignee" | "collaborator" | "involved"

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    createdIntervals: Record<string, string>
    personOptions: { label: string; value: number }[]
    stats: {
        interval: string
        person: number | null
        bucket: "day" | "week" | "month"
        from: string
        stale_before: string
        open_now: number
        stale_now: number
        oldest_open: { reference: string; age_days: number } | null
        totals: Metrics
        series: { date: string; created: number; done: number; open: number }[]
        by_status: { status: string; label: string; total: number }[]
        people: Record<Role, PersonRow[]>
        people_total: Metrics
        requesters: PersonRow[]
        departments: DepartmentRow[]
        cleared: { rows: (Pick<PersonRow, "id" | "name" | "short_name" | "avatar"> & { done: number; median_hours: number | null })[]; total: { done: number; median_hours: number | null } } | null
    }
}>()

useLiveStaffTasks(() => router.reload({ only: ["stats"] }))

const OPEN = "todo,in_progress"
const ALL_STATUSES = "todo,in_progress,done,cancelled"

const STATUS_COLORS: Record<string, string> = { todo: "#9ca3af", in_progress: "#3b82f6", done: "#16a34a", cancelled: "#f87171" }

const personFilter = computed(() => (props.stats.person ? { involved: props.stats.person } : {}))

const listParams = (filter: Record<string, any>, status: string) => ({ filter: { ...personFilter.value, ...filter }, elements: { status } })
const listUrl = (filter: Record<string, any>, status: string) => tasksRoute("list_all", listParams(filter, status))
const listRoute = (filter: Record<string, any>, status: string) => tasksRouteObject("list_all", listParams(filter, status))

const filterByPerson = (value: string) => router.reload({ data: { person: value || undefined }, preserveScroll: true })

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
            { label: ctrans("Raised"), data: props.stats.series.map((point) => point.created), borderColor: "#c0399f", backgroundColor: "#c0399f", tension: 0, borderWidth: 1.5, pointRadius },
            { label: ctrans("Done"), data: props.stats.series.map((point) => point.done), borderColor: "#1f845a", backgroundColor: "#1f845a", tension: 0, borderWidth: 1.5, pointRadius },
            { label: ctrans("Open"), data: props.stats.series.map((point) => point.open), borderColor: "#f59e0b", backgroundColor: "#f59e0b", tension: 0, borderWidth: 1.5, pointRadius },
        ],
    }
})

const lineOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: "index", intersect: false },
    plugins: {
        legend: {
            position: "bottom",
            labels: { boxWidth: 12 },
            onClick: (_event: unknown, legendItem: { datasetIndex: number }, legend: { chart: any }) => {
                const chart = legend.chart
                const isVisible = chart.isDatasetVisible(legendItem.datasetIndex)
                const visibleCount = chart.data.datasets.filter((_dataset: unknown, index: number) => chart.isDatasetVisible(index)).length
                if (isVisible && visibleCount <= 1) return
                chart.setDatasetVisibility(legendItem.datasetIndex, !isVisible)
                chart.update()
            },
        },
        tooltip: { callbacks: { title: (items: any[]) => (props.stats.bucket === "day" ? items[0].label : `${ctrans(props.stats.bucket === "week" ? "Week of" : "Month")} ${items[0].label}`) } },
    },
    scales: {
        x: { grid: { display: false }, ticks: { autoSkip: true, maxTicksLimit: 12, maxRotation: 0 } },
        y: { beginAtZero: true, ticks: { precision: 0 } },
    },
}))

const totalTasks = computed(() => props.stats.by_status.reduce((sum, row) => sum + row.total, 0))

const donutChart = computed(() => ({
    labels: props.stats.by_status.map((row) => row.label),
    datasets: [{ data: props.stats.by_status.map((row) => row.total), backgroundColor: props.stats.by_status.map((row) => STATUS_COLORS[row.status] ?? "#9ca3af") }],
}))

const openStatus = (status: string) => router.visit(listUrl({ created_since: props.stats.from }, status))

const donutOptions = {
    responsive: true,
    maintainAspectRatio: false,
    cutout: "70%",
    hoverOffset: 8,
    onHover: (_event: unknown, activeElements: { index: number }[], chart: { canvas: HTMLCanvasElement }) => {
        chart.canvas.style.cursor = activeElements.length ? "pointer" : "default"
    },
    onClick: (_event: unknown, activeElements: { index: number }[]) => {
        const row = activeElements[0] ? props.stats.by_status[activeElements[0].index] : null
        if (row) openStatus(row.status)
    },
    plugins: {
        legend: { display: false },
        tooltip: { padding: 10, boxPadding: 6, callbacks: { label: (item: { raw: number }) => `${item.raw} ${item.raw === 1 ? ctrans("task") : ctrans("tasks")}` } },
    },
}

const hours = (value: number | null) => (value === null ? "-" : value >= 48 ? `${(value / 24).toFixed(1)} ${ctrans("days")}` : `${value} ${ctrans("h")}`)
const inDays = (value: number | null) => (value === null ? "-" : (value / 24).toFixed(1))
const sharePercent = (value: number, total: number) => (total && value ? `${((value / total) * 100).toFixed(1)}%` : "")

type Tab = "people" | "requesters" | "departments"

const tab = ref<Tab>("people")
const tabs: { key: Tab; label: string }[] = [
    { key: "people", label: ctrans("People") },
    { key: "requesters", label: ctrans("Requesters") },
    { key: "departments", label: ctrans("Departments") },
]

const role = ref<Role>("assignee")
const roleTabs: { key: Role; label: string }[] = [
    { key: "assignee", label: ctrans("Assigned") },
    { key: "collaborator", label: ctrans("Collaborating") },
    { key: "involved", label: ctrans("Both") },
]

type Column = { key: string; label: string; status?: string; extraFilter?: () => Record<string, any> }

const staleFilter = () => ({ created_before: props.stats.stale_before })

const countColumns = computed<Column[]>(() => [
    ...(tab.value === "people" ? [
        { key: "todo", label: ctrans("To do"), status: "todo" },
        { key: "in_progress", label: ctrans("Working on"), status: "in_progress" },
    ] : [{ key: "created", label: ctrans("Raised"), status: ALL_STATUSES }]),
    { key: "open", label: ctrans("Still open"), status: OPEN },
    { key: "done", label: ctrans("Done"), status: "done" },
    { key: "cancelled", label: ctrans("Can't be done"), status: "cancelled" },
    { key: "stale", label: ctrans("> 7 days"), status: OPEN, extraFilter: staleFilter },
])

const sortState = ref<{ key: string; direction: 1 | -1 }>({ key: "", direction: -1 })
const sortArrow = (key: string) => (sortState.value.key === key ? (sortState.value.direction === 1 ? " ▲" : " ▼") : "")
const toggleSort = (key: string) => {
    const current = sortState.value
    sortState.value = current.key === key ? { key, direction: current.direction === 1 ? -1 : 1 } : { key, direction: key === "name" ? 1 : -1 }
}

const sortRows = <T extends Record<string, any>>(rows: T[]): T[] => {
    const { key, direction } = sortState.value
    if (!key) return rows
    return [...rows].sort((a, b) => {
        if (a[key] === null || a[key] === undefined) return 1
        if (b[key] === null || b[key] === undefined) return -1
        return (typeof a[key] === "string" ? a[key].localeCompare(b[key]) : a[key] - b[key]) * direction
    })
}

const tableRows = computed<(PersonRow | DepartmentRow)[]>(() => {
    if (tab.value === "people") return sortRows(props.stats.people[role.value])
    if (tab.value === "requesters") return sortRows(props.stats.requesters)
    return sortRows(props.stats.departments)
})

const rowKey = (row: PersonRow | DepartmentRow) => ("id" in row ? row.id : row.department)

const rowFilter = (row: PersonRow | DepartmentRow) => {
    const since = { created_since: props.stats.from }
    if (tab.value === "people") return { ...since, [role.value]: (row as PersonRow).id }
    if (tab.value === "requesters") return { ...since, requester: (row as PersonRow).id }
    return { ...since, department: (row as DepartmentRow).department }
}

const tableTotal = computed<Metrics>(() => (tab.value === "people" ? props.stats.people_total : props.stats.totals))
const totalFilter = computed(() => (tab.value === "people" ? { created_since: props.stats.from, has_assignee: 1 } : { created_since: props.stats.from }))
const showTotal = computed(() => tableRows.value.length > 0 && !(tab.value === "people" && role.value !== "assignee"))

const shareBase = computed(() => (tab.value === "people" ? props.stats.people_total.done : props.stats.totals.created))
const shareKey = computed(() => (tab.value === "people" ? "done" : "created"))

const clearedFilter = (row?: { id: number }) => ({ closed_since: props.stats.from, created_before: props.stats.from, ...(row ? { assignee: row.id } : {}) })

const linkClass = "text-[--app-accent-strong] underline-offset-2 transition duration-200 hover:text-[--app-accent-deep] hover:underline"
const pillLinkClass = "inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm tabular-nums"
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <div class="space-y-4 p-4">
        <div class="flex flex-wrap gap-3">
            <ProcurementOverviewPill :card="{ label: ctrans('Open now'), description: '', icon: 'fal fa-inbox-in', value: stats.open_now, tone: 'amber', route: tasksRouteObject('list_all', { filter: personFilter, elements: { status: OPEN } }), metrics: [] }" />
            <ProcurementOverviewPill :card="{ label: ctrans('Older than 7 days'), description: '', icon: 'fal fa-hourglass-half', value: stats.stale_now, tone: stats.stale_now ? 'amber' : 'emerald', route: tasksRouteObject('list_all', { filter: { ...personFilter, created_before: stats.stale_before }, elements: { status: OPEN } }), metrics: [] }" />
            <Link v-if="stats.oldest_open" v-tooltip="ctrans('Oldest open')" :href="taskRoute(stats.oldest_open.reference)" :class="pillLinkClass">
                <FontAwesomeIcon icon="fal fa-hourglass-half" class="text-red-500" fixed-width aria-hidden="true" />{{ stats.oldest_open.age_days }} {{ ctrans("days") }}
                <span class="border-l border-gray-200 pl-2 font-normal text-gray-500">{{ stats.oldest_open.reference }}</span>
            </Link>
        </div>

        <div class="space-y-4 rounded-xl border border-gray-200 bg-gray-50 p-3">
            <div class="flex flex-wrap items-center gap-3">
                <TicketsCreatedInterval :options="createdIntervals" :selected="stats.interval" class="min-w-0 flex-1" />
                <label class="ml-auto flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-gray-400">
                    {{ ctrans("Filter by") }}
                    <select
                        :value="stats.person ?? ''"
                        class="cursor-pointer rounded-md border-gray-300 py-1.5 pl-2 pr-8 text-sm normal-case tracking-normal text-gray-700 transition duration-200 focus:border-[--app-accent] focus:ring-[--app-accent]"
                        :class="stats.person && '!border-[--app-accent] !bg-[--app-accent-soft] !text-[--app-accent-strong]'"
                        :aria-label="ctrans('Filter by person')"
                        @change="filterByPerson(($event.target as HTMLSelectElement).value)">
                        <option value="">{{ ctrans("All people") }}</option>
                        <option v-for="option in personOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                </label>
            </div>

            <div class="flex flex-wrap gap-3">
                <ProcurementOverviewPill :card="{ label: ctrans('Raised'), description: '', icon: 'fal fa-tasks', value: stats.totals.created, tone: 'violet', route: listRoute({ created_since: stats.from }, ALL_STATUSES), metrics: [] }" />
                <ProcurementOverviewPill :card="{ label: ctrans('Done'), description: '', icon: 'fal fa-check', value: stats.totals.done, tone: 'emerald', route: listRoute({ created_since: stats.from }, 'done'), metrics: [] }" />
                <Link v-tooltip="ctrans(`Can't be done`)" :href="listUrl({ created_since: stats.from }, 'cancelled')" :class="pillLinkClass">
                    <FontAwesomeIcon icon="fal fa-ban" class="text-red-500" fixed-width aria-hidden="true" />{{ stats.totals.cancelled }}
                </Link>
                <Link v-tooltip="ctrans('Median time to done')" :href="listUrl({ created_since: stats.from }, 'done')" :class="pillLinkClass">
                    <FontAwesomeIcon icon="fal fa-stopwatch" class="text-[--app-accent-strong]" fixed-width aria-hidden="true" />{{ hours(stats.totals.median_hours) }}
                </Link>
            </div>

            <DashboardWidgetBox storageKey="tasks_reports_raised_vs_done_collapsed">
                <template #header>
                    <span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
                        <FontAwesomeIcon icon="fal fa-chart-line" class="text-pink-600" fixed-width aria-hidden="true" />
                        {{ ctrans("Raised vs Done") }}
                    </span>
                    <span class="text-xs text-gray-400">{{ stats.totals.created }} {{ ctrans("raised") }} · {{ stats.totals.done }} {{ ctrans("done") }}</span>
                </template>
                <div class="grid gap-6 lg:grid-cols-5">
                    <div class="h-72 lg:col-span-3">
                        <Chart type="line" :data="lineChart" :options="lineOptions" class="h-full" />
                    </div>
                    <div class="lg:col-span-2 lg:border-l lg:border-gray-100 lg:pl-6">
                        <p class="mb-2 flex flex-wrap items-center gap-2 text-sm font-semibold text-gray-600">
                            <FontAwesomeIcon icon="fal fa-chart-pie" class="text-blue-600" fixed-width aria-hidden="true" />
                            {{ ctrans("Status overview") }}
                            <span class="text-xs font-normal text-gray-400">{{ ctrans("Tasks raised in this period") }} · <Link :href="listUrl({ created_since: stats.from }, ALL_STATUSES)" class="hover:text-gray-600">{{ ctrans("View all") }}</Link></span>
                        </p>
                        <div class="flex items-center gap-4">
                            <div class="relative h-44 w-44 shrink-0">
                                <Chart type="doughnut" :data="donutChart" :options="donutOptions" class="relative z-10 h-full" />
                                <div class="pointer-events-none absolute inset-0 z-0 flex flex-col items-center justify-center">
                                    <span class="text-3xl font-bold">{{ totalTasks }}</span>
                                    <span class="text-xs text-gray-500">{{ ctrans("Total") }}</span>
                                </div>
                            </div>
                            <table class="text-sm tabular-nums">
                                <tbody>
                                    <tr
                                        v-for="row in stats.by_status.filter((status) => status.total)"
                                        :key="row.status"
                                        tabindex="0"
                                        role="link"
                                        class="group cursor-pointer outline-none"
                                        @click="openStatus(row.status)"
                                        @keydown.enter="openStatus(row.status)">
                                        <td class="rounded-l-md py-1 pl-2 pr-5 transition duration-200 group-hover:bg-[--app-accent-soft] group-focus-visible:bg-[--app-accent-soft]">
                                            <span class="flex items-center gap-2 transition duration-200 group-hover:text-[--app-accent-strong] group-focus-visible:text-[--app-accent-strong]">
                                                <span class="h-3 w-3 shrink-0 rounded-sm transition duration-200 group-hover:scale-110" :style="{ backgroundColor: STATUS_COLORS[row.status] ?? '#9ca3af' }" />
                                                {{ row.label }}
                                            </span>
                                        </td>
                                        <td class="py-1 pr-5 text-right font-medium transition duration-200 group-hover:bg-[--app-accent-soft] group-hover:text-[--app-accent-strong] group-focus-visible:bg-[--app-accent-soft] group-focus-visible:text-[--app-accent-strong]">{{ row.total }}</td>
                                        <td class="rounded-r-md py-1 pr-2 text-right text-gray-500 transition duration-200 group-hover:bg-[--app-accent-soft] group-hover:text-[--app-accent-strong] group-focus-visible:bg-[--app-accent-soft] group-focus-visible:text-[--app-accent-strong]">{{ totalTasks ? ((row.total / totalTasks) * 100).toFixed(1) : 0 }}%</td>
                                    </tr>
                                    <tr v-if="!totalTasks">
                                        <td class="py-1 pl-2 text-gray-400">{{ ctrans("No tasks in this period") }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </DashboardWidgetBox>

            <DashboardWidgetBox storageKey="tasks_reports_people_collapsed">
                <template #header>
                    <span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
                        <FontAwesomeIcon icon="fal fa-users" class="text-violet-600" fixed-width aria-hidden="true" />
                        {{ ctrans("Breakdown") }}
                    </span>
                    <span class="flex items-center gap-1.5">
                        <button
                            v-for="option in tabs"
                            :key="option.key"
                            type="button"
                            class="rounded-full border px-2.5 py-px text-xs"
                            :class="tab === option.key ? 'border-[--app-accent] bg-[--app-accent-soft] text-[--app-accent-strong]' : 'border-gray-200 text-gray-500 hover:bg-gray-50'"
                            @click="tab = option.key; sortState = { key: '', direction: -1 }">
                            {{ option.label }}
                        </button>
                    </span>
                    <span v-if="tab === 'people'" class="ml-2 inline-flex overflow-hidden rounded-full border border-gray-200 text-xs">
                        <button
                            v-for="option in roleTabs"
                            :key="option.key"
                            type="button"
                            class="px-2.5 py-px transition duration-200"
                            :class="role === option.key ? 'bg-[--app-accent-soft] text-[--app-accent-strong]' : 'text-gray-500 hover:bg-gray-50'"
                            @click="role = option.key">
                            {{ option.label }}
                        </button>
                    </span>
                    <span class="text-xs text-gray-400">{{ ctrans("Tasks raised in this period") }}</span>
                </template>

                <div class="-mx-4 -mb-4 overflow-x-auto">
                    <table class="min-w-full text-sm tabular-nums">
                        <thead class="text-left text-xs text-gray-500">
                            <tr>
                                <th class="cursor-pointer select-none px-4 py-2 hover:text-gray-700" @click="toggleSort('name')">
                                    {{ tab === "departments" ? ctrans("Department") : tab === "requesters" ? ctrans("Requester") : ctrans("Person") }}{{ sortArrow("name") }}
                                </th>
                                <th v-for="column in countColumns" :key="column.key" class="cursor-pointer select-none px-4 py-2 text-right hover:text-gray-700" @click="toggleSort(column.key)">
                                    {{ column.label }}{{ sortArrow(column.key) }}
                                </th>
                                <th class="cursor-pointer select-none px-4 py-2 text-right hover:text-gray-700" @click="toggleSort('median_hours')">{{ ctrans("Median time to done (days)") }}{{ sortArrow("median_hours") }}</th>
                                <th class="cursor-pointer select-none px-4 py-2 text-right hover:text-gray-700" @click="toggleSort('longest_wait_days')">{{ ctrans("Longest wait (days)") }}{{ sortArrow("longest_wait_days") }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in tableRows" :key="rowKey(row)" class="border-t border-gray-100">
                                <td class="px-4 py-2 font-medium">
                                    <span v-if="'id' in row" v-tooltip="{ content: row.name, delay: 0 }" class="inline-flex items-center gap-2">
                                        <TicketUserAvatar :name="row.name" :avatar="row.avatar" size="sm" />
                                        {{ row.short_name }}
                                    </span>
                                    <span v-else class="inline-flex items-center gap-2">
                                        <FontAwesomeIcon icon="fal fa-building" class="text-gray-400" fixed-width aria-hidden="true" />
                                        {{ row.name }}
                                    </span>
                                </td>
                                <td v-for="column in countColumns" :key="column.key" class="px-4 py-2 text-right">
                                    <Link v-if="row[column.key as keyof Metrics]" :href="listUrl({ ...rowFilter(row), ...(column.extraFilter?.() ?? {}) }, column.status!)" :class="[linkClass, column.key === 'stale' && 'font-semibold !text-red-600']">{{ row[column.key as keyof Metrics] }}</Link>
                                    <span v-else class="text-gray-400">0</span>
                                    <span v-if="column.key === shareKey" class="inline-block w-16 text-gray-400">{{ sharePercent(row[shareKey as keyof Metrics] as number, shareBase) }}</span>
                                </td>
                                <td class="px-4 py-2 text-right">{{ inDays(row.median_hours) }}</td>
                                <td class="px-4 py-2 text-right">{{ row.longest_wait_days ?? "-" }}</td>
                            </tr>
                            <tr v-if="!tableRows.length">
                                <td :colspan="countColumns.length + 3" class="px-4 py-6 text-center text-gray-400">{{ ctrans("No tasks in this period") }}</td>
                            </tr>
                        </tbody>
                        <tfoot v-if="showTotal" class="border-t-2 border-gray-200 font-semibold">
                            <tr>
                                <td class="px-4 py-2">{{ ctrans("Total") }}</td>
                                <td v-for="column in countColumns" :key="column.key" class="px-4 py-2 text-right">
                                    <Link v-if="tableTotal[column.key as keyof Metrics]" :href="listUrl({ ...totalFilter, ...(column.extraFilter?.() ?? {}) }, column.status!)" :class="linkClass">{{ tableTotal[column.key as keyof Metrics] }}</Link>
                                    <span v-else class="text-gray-400">0</span>
                                    <span v-if="column.key === shareKey" class="inline-block w-16" />
                                </td>
                                <td class="px-4 py-2 text-right">{{ inDays(tableTotal.median_hours) }}</td>
                                <td class="px-4 py-2 text-right">{{ tableTotal.longest_wait_days ?? "-" }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </DashboardWidgetBox>

            <DashboardWidgetBox v-if="stats.cleared" storageKey="tasks_reports_cleared_collapsed">
                <template #header>
                    <span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
                        <FontAwesomeIcon icon="fal fa-check" class="text-emerald-600" fixed-width aria-hidden="true" />
                        {{ ctrans("Cleared") }}
                    </span>
                    <span class="text-xs text-gray-400">{{ ctrans("Older tasks, raised before this period, done in it") }}</span>
                </template>
                <div class="-mx-4 -mb-4 overflow-x-auto">
                    <table class="min-w-full text-sm tabular-nums">
                        <thead class="text-left text-xs text-gray-500">
                            <tr>
                                <th class="px-4 py-2">{{ ctrans("Person") }}</th>
                                <th class="px-4 py-2 text-right">{{ ctrans("Done") }}</th>
                                <th class="px-4 py-2 text-right">{{ ctrans("Median time to done (days)") }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in stats.cleared.rows" :key="row.id" class="border-t border-gray-100">
                                <td class="px-4 py-2 font-medium">
                                    <span v-tooltip="{ content: row.name, delay: 0 }" class="inline-flex items-center gap-2">
                                        <TicketUserAvatar :name="row.name" :avatar="row.avatar" size="sm" />
                                        {{ row.short_name }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <Link :href="listUrl(clearedFilter(row), 'done')" :class="linkClass">{{ row.done }}</Link>
                                    <span class="inline-block w-16 text-gray-400">{{ sharePercent(row.done, stats.cleared.total.done) }}</span>
                                </td>
                                <td class="px-4 py-2 text-right">{{ inDays(row.median_hours) }}</td>
                            </tr>
                            <tr v-if="!stats.cleared.rows.length">
                                <td colspan="3" class="px-4 py-6 text-center text-gray-400">{{ ctrans("No older tasks done in this period") }}</td>
                            </tr>
                        </tbody>
                        <tfoot v-if="stats.cleared.rows.length" class="border-t-2 border-gray-200 font-semibold">
                            <tr>
                                <td class="px-4 py-2">{{ ctrans("Total") }}</td>
                                <td class="px-4 py-2 text-right">
                                    <Link v-if="stats.cleared.total.done" :href="listUrl(clearedFilter(), 'done')" :class="linkClass">{{ stats.cleared.total.done }}</Link>
                                    <span v-else>0</span>
                                    <span class="inline-block w-16" />
                                </td>
                                <td class="px-4 py-2 text-right">{{ inDays(stats.cleared.total.median_hours) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </DashboardWidgetBox>
        </div>
    </div>
</template>
