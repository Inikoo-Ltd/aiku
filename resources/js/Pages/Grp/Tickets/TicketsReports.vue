<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ticketRoute, ticketsRoute, ticketsRouteObject } from "@/Composables/useTicketsRoute"
import { Head, Link, router } from "@inertiajs/vue3"
import { computed, ref } from "vue"
import { ctrans } from "@/Composables/useTrans"
import { capitalize } from "@/Composables/capitalize"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TicketUserAvatar from "@/Components/Tickets/TicketUserAvatar.vue"
import Chart from "primevue/chart"
import TicketsCreatedInterval from "@/Components/Tickets/TicketsCreatedInterval.vue"
import { useLiveTickets } from "@/Composables/useLiveTickets"
import ProcurementOverviewPill from "@/Components/DataDisplay/Dashboard/Widget/ProcurementOverviewPill.vue"
import DashboardWidgetBox from "@/Components/DataDisplay/Dashboard/Widget/DashboardWidgetBox.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTicketAlt, faCheck, faInboxIn, faStopwatch, faStar, faHourglassHalf, faChartLine, faChartPie, faUsers, faCubes, faTags } from "@fal"
import { ticketKindIcon } from "@/Composables/useTicketKindIcons"

library.add(faTicketAlt, faCheck, faInboxIn, faStopwatch, faStar, faHourglassHalf, faChartLine, faChartPie, faUsers, faCubes, faTags)

const props = defineProps<{
    pageHead: any
    title: string
    createdIntervals: Record<string, string>
    assigneeOptions: { label: string; value: string }[]
    stats: {
        interval: string
        assignee: string | null
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
        daily: { date: string; created: number; done: number; open: number }[]
        breakdown: [string, string, string, string, string | null, number][]
        modules: { value: string; label: string; total: number }[]
        kinds: { value: string; label: string; total: number }[]
        by_status: { status: string; label: string; color: string; total: number }[]
        assignees: (Metrics & { name: string; username: string; short_name: string; avatar: any; collaborating?: Record<"assigned" | "in_progress" | "open" | "done", number> })[]
        assignees_total: Metrics
        resolvers: (Metrics & { name: string; username: string; short_name: string; avatar: any; collaborating?: Record<"assigned" | "in_progress" | "open" | "done", number> })[]
        resolvers_total: Metrics
        reporters: (Metrics & { key: string; name: string; is_staff: boolean; avatar?: any })[]
    }
}>()

type Metrics = { created: number; open: number; assigned: number; in_progress: number; resolved: number; cancelled: number; done: number; median_hours: number | null; longest_wait_days: number | null; rating: number | null; ratings: number }

const peopleTab = ref<"assignees" | "reporters">("assignees")

const filterByAssignee = (username: string) => router.reload({ data: { assignee: username || undefined }, preserveScroll: true })

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
            { label: ctrans("Created"), data: props.stats.daily.map((day) => day.created), borderColor: "#c0399f", backgroundColor: "#c0399f", tension: 0, borderWidth: 1.5, pointRadius },
            { label: ctrans("Resolved"), data: props.stats.daily.map((day) => day.done), borderColor: "#1f845a", backgroundColor: "#1f845a", tension: 0, borderWidth: 1.5, pointRadius },
            { label: ctrans("Open"), data: props.stats.daily.map((day) => day.open), borderColor: "#f59e0b", backgroundColor: "#f59e0b", tension: 0, borderWidth: 1.5, pointRadius },
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

type Dimension = "module" | "kind"

type BreakdownRow = [string, string, string, string, string | null, number]

type FilterDimension = Dimension | "status"

const DIMENSION_COLUMN: Record<FilterDimension, 1 | 2 | 3> = { module: 1, kind: 2, status: 3 }

const MODULE_PALETTE = ["#3b82f6", "#c0399f", "#16a34a", "#f59e0b", "#8b5cf6", "#06b6d4", "#ef4444", "#84cc16", "#f97316", "#14b8a6", "#6366f1", "#a16207"]

type ReportTab = "overview" | "modules" | "kinds" | "reporters"

const reportTabs: { key: ReportTab; label: string }[] = [
    { key: "overview", label: ctrans("Overview") },
    { key: "modules", label: ctrans("By module") },
    { key: "kinds", label: ctrans("By kind") },
    { key: "reporters", label: ctrans("By reporter") },
]

const requestedTab = new URLSearchParams(window.location.search).get("tab")

const reportTab = ref<ReportTab>(reportTabs.some((tab) => tab.key === requestedTab) ? (requestedTab as ReportTab) : "overview")

const groupBy = computed<Dimension>(() => (reportTab.value === "kinds" ? "kind" : "module"))

const dimensionOptions = (dimension: Dimension) => (dimension === "module" ? props.stats.modules : props.stats.kinds)

const dimensionColor = (dimension: Dimension, value: string) =>
    value === "none" ? "#d1d5db" : MODULE_PALETTE[Math.max(dimensionOptions(dimension).findIndex((row) => row.value === value), 0) % MODULE_PALETTE.length]

const selectedModules = ref<string[] | null>(null)

const selectedKinds = ref<string[] | null>(null)

const selectedStatuses = ref<string[] | null>(null)

const selections = { module: selectedModules, kind: selectedKinds, status: selectedStatuses }

const filterValues = (dimension: FilterDimension) => (dimension === "status" ? props.stats.by_status.map((row) => row.status) : dimensionOptions(dimension).map((row) => row.value))

const isSelected = (dimension: FilterDimension, value: string) => selections[dimension].value === null || selections[dimension].value.includes(value)

const toggleSelection = (dimension: FilterDimension, value: string) => {
    const current = selections[dimension].value ?? filterValues(dimension)
    selections[dimension].value = current.includes(value) ? current.filter((item) => item !== value) : [...current, value]
}

const matches = (row: BreakdownRow, ignore?: FilterDimension) =>
    (Object.keys(selections) as FilterDimension[]).every((dimension) => dimension === ignore || selections[dimension].value === null || selections[dimension].value.includes(row[DIMENSION_COLUMN[dimension]] as string))

const filteredRows = computed(() => props.stats.breakdown.filter((row) => matches(row)))

const chipTotals = computed(() =>
    Object.fromEntries(
        (Object.keys(selections) as FilterDimension[]).map((dimension) => {
            const totals: Record<string, number> = {}
            props.stats.breakdown.forEach((row) => {
                if (matches(row, dimension)) {
                    const value = row[DIMENSION_COLUMN[dimension]] as string
                    totals[value] = (totals[value] ?? 0) + row[5]
                }
            })
            return [dimension, totals]
        })
    ) as Record<FilterDimension, Record<string, number>>
)

const moduleMode = ref<"count" | "share">("count")

const moduleModeTabs: { key: "count" | "share"; label: string }[] = [
    { key: "count", label: ctrans("Count") },
    { key: "share", label: ctrans("Share %") },
]

const activeValues = computed(() => dimensionOptions(groupBy.value).filter((row) => isSelected(groupBy.value, row.value)))

const moduleChart = computed(() => {
    const column = DIMENSION_COLUMN[groupBy.value]
    const sums: Record<string, Record<string, number>> = {}
    filteredRows.value.forEach((row) => {
        const value = row[column] as string
        sums[value] ??= {}
        sums[value][row[0]] = (sums[value][row[0]] ?? 0) + row[5]
    })
    const count = (value: string, date: string) => sums[value]?.[date] ?? 0
    return {
        labels: props.stats.daily.map((day) => bucketLabel(day.date)),
        datasets: activeValues.value.map((row) => ({
            label: row.label,
            data: props.stats.daily.map((day) => {
                if (moduleMode.value === "count") return count(row.value, day.date)
                const bucketTotal = activeValues.value.reduce((sum, active) => sum + count(active.value, day.date), 0)
                return bucketTotal ? (count(row.value, day.date) / bucketTotal) * 100 : 0
            }),
            backgroundColor: dimensionColor(groupBy.value, row.value),
            borderWidth: 0,
        })),
    }
})

const moduleOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    onHover: (event: { native?: { target?: HTMLElement } }, elements: { datasetIndex: number }[]) => {
        const value = elements[0] ? activeValues.value[elements[0].datasetIndex]?.value : null
        if (event.native?.target) event.native.target.style.cursor = value && value !== "none" ? "pointer" : "default"
    },
    onClick: (_event: unknown, elements: { datasetIndex: number }[]) => {
        const value = elements[0] ? activeValues.value[elements[0].datasetIndex]?.value : null
        if (!value || value === "none") return
        const other: Dimension = groupBy.value === "module" ? "kind" : "module"
        router.visit(
            listUrl({
                filter: { created_since: props.stats.from },
                elements: {
                    [groupBy.value]: value,
                    ...(selectedStatuses.value ? { status: selectedStatuses.value.join(",") } : {}),
                    ...(selections[other].value ? { [other]: selections[other].value.join(",") } : {}),
                },
            })
        )
    },
    plugins: {
        legend: { display: false },
        tooltip: {
            mode: "index",
            intersect: false,
            filter: (item: { raw: number }) => item.raw > 0,
            callbacks: {
                title: (items: any[]) => (props.stats.bucket === "day" ? items[0].label : `${ctrans(props.stats.bucket === "week" ? "Week of" : "Month")} ${items[0].label}`),
                ...(moduleMode.value === "share" ? { label: (item: { dataset: { label: string }; raw: number }) => `${item.dataset.label}: ${item.raw.toFixed(1)}%` } : {}),
            },
        },
    },
    scales: {
        x: { stacked: true, grid: { display: false }, ticks: { autoSkip: true, maxTicksLimit: 12, maxRotation: 0 } },
        y: moduleMode.value === "share"
            ? { stacked: true, beginAtZero: true, max: 100, ticks: { precision: 0, callback: (value: number | string) => `${value}%` } }
            : { stacked: true, beginAtZero: true, ticks: { precision: 0 } },
    },
}))

const reporterBreakdownColumns = computed(() => {
    const column = DIMENSION_COLUMN[groupBy.value]
    const totals: Record<string, number> = {}
    filteredRows.value.forEach((row) => {
        totals[row[column] as string] = (totals[row[column] as string] ?? 0) + row[5]
    })
    const ranked = dimensionOptions(groupBy.value)
        .filter((row) => totals[row.value])
        .sort((a, b) => totals[b.value] - totals[a.value])
        .map((row) => ({ key: `c_${row.value}`, value: row.value, label: row.label }))
    const top = ranked.slice(0, 8)
    return { top, hasOther: ranked.length > top.length, topValues: new Set(top.map((item) => item.value)) }
})

const reporterBreakdownColumnList = computed(() => [
    { key: "name", label: ctrans("Reporter") },
    ...reporterBreakdownColumns.value.top,
    ...(reporterBreakdownColumns.value.hasOther ? [{ key: "other", label: ctrans("Other") }] : []),
    { key: "total", label: ctrans("Total") },
])

const reporterBreakdownRows = computed(() => {
    const column = DIMENSION_COLUMN[groupBy.value]
    const { top, topValues } = reporterBreakdownColumns.value
    const grouped: Record<string, Record<string, any>> = {}
    filteredRows.value.forEach((row) => {
        if (row[4] === null) return
        const reporter = props.stats.reporters.find((item) => item.key === row[4])
        grouped[row[4]] ??= { key: row[4], name: reporter?.name ?? row[4], is_staff: reporter?.is_staff ?? true, avatar: reporter?.avatar, other: 0, total: 0, ...Object.fromEntries(top.map((item) => [item.key, 0])) }
        const target = grouped[row[4]]
        const cellKey = topValues.has(row[column] as string) ? `c_${row[column]}` : "other"
        target[cellKey] += row[5]
        target.total += row[5]
    })
    return sortRows(Object.values(grouped).sort((a, b) => b.total - a.total), "reporter_breakdown")
})

const reporterBreakdownTotals = computed(() => {
    const totals: Record<string, number> = { other: 0, total: 0 }
    reporterBreakdownRows.value.forEach((row) => {
        Object.entries(row).forEach(([key, value]) => {
            if (typeof value === "number") totals[key] = (totals[key] ?? 0) + value
        })
    })
    return totals
})

const donutChart = computed(() => ({
    labels: props.stats.by_status.map((row) => row.label),
    datasets: [{ data: props.stats.by_status.map((row) => row.total), backgroundColor: props.stats.by_status.map((row) => STATUS_COLORS[row.status] ?? "#9ca3af") }],
}))

const openStatusSlice = (statusIndex: number | undefined) => {
    const row = statusIndex === undefined ? null : props.stats.by_status[statusIndex]
    if (row) router.visit(listUrl({ filter: { created_since: props.stats.from }, elements: { status: row.status } }))
}

const donutOptions = {
    responsive: true,
    maintainAspectRatio: false,
    cutout: "70%",
    hoverOffset: 8,
    onHover: (_event: unknown, activeElements: { index: number }[], chart: { canvas: HTMLCanvasElement }) => {
        chart.canvas.style.cursor = activeElements.length ? "pointer" : "default"
    },
    onClick: (_event: unknown, activeElements: { index: number }[]) => openStatusSlice(activeElements[0]?.index),
    plugins: {
        legend: { display: false },
        tooltip: {
            padding: 10,
            boxPadding: 6,
            callbacks: {
                label: (item: { raw: number }) => `${item.raw} ${item.raw === 1 ? ctrans("ticket") : ctrans("tickets")}`,
            },
        },
    },
}

const csatChart = computed(() => ({
    labels: props.stats.csat_by_month.map((row) => row.month.slice(2)),
    datasets: [{ label: ctrans("Average rating"), data: props.stats.csat_by_month.map((row) => row.average), backgroundColor: "#3b82f6", borderRadius: 4 }],
}))

const csatOptions = {
    responsive: true,
    onClick: (_event: unknown, elements: { index: number }[]) => {
        const row = props.stats.csat_by_month[elements[0]?.index]
        if (row?.total) router.visit(listUrl({ filter: { rated_month: row.month, ...(props.stats.assignee ? { assignee: props.stats.assignee } : {}) } }))
    },
    onHover: (event: { native?: { target?: HTMLElement } }, elements: unknown[]) => {
        if (event.native?.target) event.native.target.style.cursor = elements.length ? "pointer" : "default"
    },
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: { x: { grid: { display: false } }, y: { beginAtZero: true, max: 5, ticks: { stepSize: 1 } } },
}

const totalTickets = computed(() => props.stats.by_status.reduce((sum, row) => sum + row.total, 0))

const hours = (value: number | null) => (value === null ? "-" : value >= 48 ? `${(value / 24).toFixed(1)} ${ctrans("days")}` : `${value} ${ctrans("h")}`)

const OPEN_STATUSES = computed(() => props.stats.by_status.filter((s) => !["resolved", "cancelled"].includes(s.status)).map((s) => s.status).join(","))

const listUrl = (params: Record<string, any>) => ticketsRoute("list", params)

const listRoute = (params: Record<string, any>) => ticketsRouteObject("list", params)

const pillValue = (value: string | number | null) => (value === null ? "-" : value)

type TableMode = "assignees" | "resolvers" | "reporters" | "reporter_breakdown"

const sortStates = ref<Record<TableMode, { key: string; direction: 1 | -1 }>>({
    assignees: { key: "", direction: -1 },
    resolvers: { key: "", direction: -1 },
    reporters: { key: "", direction: -1 },
    reporter_breakdown: { key: "", direction: -1 },
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

type Involvement = "assignee" | "collaborator" | "involved"

const involvement = ref<Involvement>("assignee")

const involvementTabs: { key: Involvement; label: string }[] = [
    { key: "assignee", label: ctrans("Assigned") },
    { key: "collaborator", label: ctrans("Collaborating") },
    { key: "involved", label: ctrans("Both") },
]

const INVOLVEMENT_COUNT_KEYS = ["assigned", "in_progress", "open", "done"] as const

const involvementFor = (mode: "assignees" | "resolvers"): Involvement => (mode === "assignees" ? involvement.value : "assignee")

const withInvolvement = <T extends Record<string, any>>(row: T, mode: "assignees" | "resolvers"): T => {
    const view = involvementFor(mode)
    if (view === "assignee") return row
    const counts = Object.fromEntries(INVOLVEMENT_COUNT_KEYS.map((key) => [key, (view === "involved" ? row[key] : 0) + (row.collaborating?.[key] ?? 0)]))
    return view === "collaborator" ? { ...row, ...counts, median_hours: null, longest_wait_days: null, rating: null, ratings: 0 } : { ...row, ...counts }
}

const engineerRows = (mode: "assignees" | "resolvers") =>
    sortRows(
        (mode === "resolvers" ? props.stats.resolvers : props.stats.assignees)
            .map((row) => withInvolvement(row, mode))
            .filter((row) => (involvementFor(mode) === "assignee" ? row.created > 0 || row.done > 0 : INVOLVEMENT_COUNT_KEYS.some((key) => row[key] > 0))),
        mode
    )

const engineerTotal = (mode: "assignees" | "resolvers") => (mode === "resolvers" ? props.stats.resolvers_total : props.stats.assignees_total)

const sharePercent = (value: number, total: number) => (total && value ? `${((value / total) * 100).toFixed(1)}%` : "")

const inDays = (hoursValue: number | null) => (hoursValue === null ? "-" : (hoursValue / 24).toFixed(1))

const peopleTabs = [
    { key: "assignees", label: ctrans("Engineers") },
    { key: "reporters", label: ctrans("Reporters") },
]

const reporterColumns = [
    { key: "name", label: ctrans("Reporter") },
    { key: "created", label: ctrans("Created") },
    { key: "open", label: ctrans("Still open") },
    { key: "resolved", label: ctrans("Resolved") },
    { key: "cancelled", label: ctrans("Cancelled") },
    { key: "median_hours", label: ctrans("Median time to resolve (days)") },
    { key: "longest_wait_days", label: ctrans("Longest wait (days)") },
    { key: "rating", label: ctrans("Average rating") },
]

const assigneeColumns = [
    { key: "short_name", label: ctrans("Engineer") },
    { key: "assigned", label: ctrans("To do") },
    { key: "in_progress", label: ctrans("Working on") },
    { key: "open", label: ctrans("Still open") },
    { key: "done", label: ctrans("Resolved") },
    { key: "median_hours", label: ctrans("Median time to resolve (days)") },
    { key: "longest_wait_days", label: ctrans("Longest wait (days)") },
    { key: "rating", label: ctrans("Average rating") },
]

const engineerColumns = (mode: "assignees" | "resolvers") =>
    mode === "resolvers" ? assigneeColumns.filter((column) => !["assigned", "in_progress", "open", "longest_wait_days"].includes(column.key)) : assigneeColumns

const assigneeFilter = (mode: "assignees" | "resolvers", username: string, role: "assignee" | "collaborator" | "involved" = "assignee") =>
    mode === "resolvers" ? { [role]: username, resolved_since: props.stats.from } : { [role]: username, created_since: props.stats.from }

const engineerTotalFilter = (mode: "assignees" | "resolvers") =>
    mode === "resolvers" ? { has_assignee: 1, resolved_since: props.stats.from } : { has_assignee: 1, created_since: props.stats.from }

const reporterFilter = (reporterKey: string) => ({ reporter: reporterKey, created_since: props.stats.from })


const selectReportTab = (tab: ReportTab) => {
    reportTab.value = tab
    const url = new URL(window.location.href)
    if (tab === "overview") {
        url.searchParams.delete("tab")
    } else {
        url.searchParams.set("tab", tab)
    }
    window.history.replaceState(window.history.state, "", url)
}

const dashboardBoxes = computed(() => (props.stats.interval === "all" ? (["people"] as const) : (["people", "cleared"] as const)))
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <div class="p-4 space-y-4">
        <div class="flex flex-wrap gap-3">
            <ProcurementOverviewPill :card="{ label: ctrans('Open now'), description: '', icon: 'fal fa-inbox-in', value: stats.open, tone: 'amber', route: listRoute({ elements: { status: OPEN_STATUSES } }), metrics: [] }" />
            <Link v-if="stats.oldest_open" v-tooltip="ctrans('Oldest open')" :href="ticketRoute(stats.oldest_open.reference)" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm tabular-nums">
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
                    :value="stats.assignee ?? ''"
                    class="cursor-pointer rounded-md border-gray-300 py-1.5 pl-2 pr-8 text-sm normal-case tracking-normal text-gray-700 transition duration-200 focus:border-[--app-accent] focus:ring-[--app-accent]"
                    :class="stats.assignee && '!border-[--app-accent] !bg-[--app-accent-soft] !text-[--app-accent-strong]'"
                    :aria-label="ctrans('Filter by assignee')"
                    @change="filterByAssignee(($event.target as HTMLSelectElement).value)">
                    <option value="">{{ ctrans("All assignees") }}</option>
                    <option v-for="option in assigneeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
            </label>
        </div>

        <div class="flex gap-1 border-b border-gray-200" role="tablist">
            <button
                v-for="tab in reportTabs"
                :key="tab.key"
                type="button"
                role="tab"
                :aria-selected="reportTab === tab.key"
                class="-mb-px border-b-2 px-3 py-2 text-sm font-medium transition duration-200"
                :class="reportTab === tab.key ? 'border-[--app-accent] text-[--app-accent-strong]' : 'border-transparent text-gray-500 hover:text-gray-700'"
                @click="selectReportTab(tab.key)">
                {{ tab.label }}
            </button>
        </div>

        <div v-if="reportTab === 'overview'" class="flex flex-wrap gap-3">
            <ProcurementOverviewPill :card="{ label: ctrans('Created'), description: '', icon: 'fal fa-ticket-alt', value: stats.created, tone: 'violet', route: listRoute({ filter: { created_since: stats.from } }), metrics: [] }" />
            <ProcurementOverviewPill :card="{ label: ctrans('Resolved'), description: '', icon: 'fal fa-check', value: stats.done, tone: 'emerald', route: listRoute({ filter: { resolved_since: stats.from } }), metrics: [] }" />
            <Link v-tooltip="ctrans('Median time to resolve')" :href="listUrl({ filter: { resolved_since: stats.from } })" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm tabular-nums">
                <FontAwesomeIcon icon="fal fa-stopwatch" class="text-[--app-accent-strong]" fixed-width aria-hidden="true" />{{ hours(stats.median_hours) }}
            </Link>
            <Link v-tooltip="ctrans('Customer satisfaction')" :href="listUrl({ filter: { rated_since: stats.from } })" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm tabular-nums">
                <FontAwesomeIcon icon="fal fa-star" class="text-sky-600" fixed-width aria-hidden="true" />{{ pillValue(stats.csat) }}<span class="font-normal text-gray-400">/5</span>
            </Link>
        </div>

        <DashboardWidgetBox v-if="reportTab === 'overview'" storageKey="tickets_reports_created_vs_done_collapsed">
            <template #header>
                <span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
                    <FontAwesomeIcon icon="fal fa-chart-line" class="text-pink-600" fixed-width aria-hidden="true" />
                    {{ ctrans("Created vs Resolved") }}
                </span>
                <span class="text-xs text-gray-400">{{ stats.created }} {{ ctrans("created") }} · {{ stats.done }} {{ ctrans("resolved") }}</span>
            </template>
            <div class="grid gap-6 lg:grid-cols-5">
                <div class="h-72 lg:col-span-3">
                    <Chart type="line" :data="lineChart" :options="lineOptions" class="h-full" />
                </div>
                <div class="lg:col-span-2 lg:border-l lg:border-gray-100 lg:pl-6">
                    <p class="mb-2 flex items-center gap-2 text-sm font-semibold text-gray-600">
                        <FontAwesomeIcon icon="fal fa-chart-pie" class="text-blue-600" fixed-width aria-hidden="true" />
                        {{ ctrans("Status overview") }}
                        <span class="text-xs font-normal text-gray-400">{{ ctrans("Tickets created in this period") }} · <Link :href="listUrl({ filter: { created_since: stats.from } })" class="hover:text-gray-600">{{ ctrans("View all") }}</Link></span>
                    </p>
                    <div class="flex items-center gap-4">
                        <div class="relative h-44 w-44 shrink-0">
                            <Chart type="doughnut" :data="donutChart" :options="donutOptions" class="relative z-10 h-full" />
                            <div class="absolute inset-0 z-0 flex flex-col items-center justify-center pointer-events-none">
                                <span class="text-3xl font-bold">{{ totalTickets }}</span>
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
                                    @click="router.visit(listUrl({ filter: { created_since: stats.from }, elements: { status: row.status } }))"
                                    @keydown.enter="router.visit(listUrl({ filter: { created_since: stats.from }, elements: { status: row.status } }))">
                                    <td class="rounded-l-md py-1 pl-2 pr-5 transition duration-200 group-hover:bg-[--app-accent-soft] group-focus-visible:bg-[--app-accent-soft]">
                                        <span class="flex items-center gap-2 transition duration-200 group-hover:text-[--app-accent-strong] group-focus-visible:text-[--app-accent-strong]">
                                            <span class="h-3 w-3 shrink-0 rounded-sm transition duration-200 group-hover:scale-110" :style="{ backgroundColor: STATUS_COLORS[row.status] ?? '#9ca3af' }" />
                                            {{ row.label }}
                                        </span>
                                    </td>
                                    <td class="py-1 pr-5 text-right font-medium transition duration-200 group-hover:bg-[--app-accent-soft] group-hover:text-[--app-accent-strong] group-focus-visible:bg-[--app-accent-soft] group-focus-visible:text-[--app-accent-strong]">{{ row.total }}</td>
                                    <td class="rounded-r-md py-1 pr-2 text-right text-gray-500 transition duration-200 group-hover:bg-[--app-accent-soft] group-hover:text-[--app-accent-strong] group-focus-visible:bg-[--app-accent-soft] group-focus-visible:text-[--app-accent-strong]">{{ totalTickets ? ((row.total / totalTickets) * 100).toFixed(1) : 0 }}%</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </DashboardWidgetBox>

        <DashboardWidgetBox v-if="reportTab !== 'overview'" storageKey="tickets_reports_by_module_collapsed">
            <template #header>
                <span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
                    <FontAwesomeIcon :icon="groupBy === 'module' ? 'fal fa-cubes' : 'fal fa-tags'" class="text-indigo-600" fixed-width aria-hidden="true" />
                    {{ reportTab === "reporters" ? ctrans("Tickets by reporter") : groupBy === "module" ? ctrans("Tickets by module") : ctrans("Tickets by kind") }}
                </span>
                <span class="text-xs text-gray-400">{{ dimensionOptions(groupBy).length }} {{ groupBy === "module" ? ctrans("modules") : ctrans("kinds") }}</span>
            </template>
            <div class="mb-3 flex flex-wrap items-center gap-1.5">
                <span v-if="reportTab !== 'reporters'" class="ml-auto inline-flex overflow-hidden rounded-full border border-gray-200 text-xs">
                    <button
                        v-for="tab in moduleModeTabs"
                        :key="tab.key"
                        type="button"
                        class="px-2.5 py-px transition duration-200"
                        :class="moduleMode === tab.key ? 'bg-[--app-accent-soft] text-[--app-accent-strong]' : 'text-gray-500 hover:bg-gray-50'"
                        @click="moduleMode = tab.key">
                        {{ tab.label }}
                    </button>
                </span>
            </div>
            <div class="mb-3 flex flex-wrap items-center gap-1.5">
                <span class="mr-1 text-xs font-medium uppercase tracking-wide text-gray-400">{{ ctrans("Modules") }}</span>
                <button
                    v-for="row in stats.modules"
                    :key="row.value"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-px text-xs"
                    :class="isSelected('module', row.value) ? 'border-[--app-accent] bg-[--app-accent-soft] text-[--app-accent-strong]' : 'border-gray-200 text-gray-500 hover:bg-gray-50'"
                    @click="toggleSelection('module', row.value)">
                    <span class="h-2 w-2 shrink-0 rounded-full" :style="{ backgroundColor: dimensionColor('module', row.value) }" />
                    {{ row.label }} {{ chipTotals.module[row.value] ?? 0 }}
                </button>
                <button type="button" class="px-1.5 text-xs text-gray-500 hover:text-gray-700" @click="selectedModules = null">{{ ctrans("All") }}</button>
                <button type="button" class="px-1.5 text-xs text-gray-500 hover:text-gray-700" @click="selectedModules = []">{{ ctrans("None") }}</button>
            </div>
            <div class="mb-3 flex flex-wrap items-center gap-1.5">
                <span class="mr-1 text-xs font-medium uppercase tracking-wide text-gray-400">{{ ctrans("Kinds") }}</span>
                <button
                    v-for="row in stats.kinds"
                    :key="row.value"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-px text-xs"
                    :class="isSelected('kind', row.value) ? 'border-[--app-accent] bg-[--app-accent-soft] text-[--app-accent-strong]' : 'border-gray-200 text-gray-500 hover:bg-gray-50'"
                    @click="toggleSelection('kind', row.value)">
                    <span v-if="groupBy === 'kind'" class="h-2 w-2 shrink-0 rounded-full" :style="{ backgroundColor: dimensionColor('kind', row.value) }" />
                    <FontAwesomeIcon :icon="ticketKindIcon(row.value)" class="text-[10px]" fixed-width aria-hidden="true" />
                    {{ row.label }} {{ chipTotals.kind[row.value] ?? 0 }}
                </button>
                <button type="button" class="px-1.5 text-xs text-gray-500 hover:text-gray-700" @click="selectedKinds = null">{{ ctrans("All") }}</button>
                <button type="button" class="px-1.5 text-xs text-gray-500 hover:text-gray-700" @click="selectedKinds = []">{{ ctrans("None") }}</button>
            </div>
            <div class="mb-3 flex flex-wrap items-center gap-1.5">
                <span class="mr-1 text-xs font-medium uppercase tracking-wide text-gray-400">{{ ctrans("Status") }}</span>
                <button
                    v-for="row in stats.by_status"
                    :key="row.status"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-px text-xs"
                    :class="isSelected('status', row.status) ? 'border-[--app-accent] bg-[--app-accent-soft] text-[--app-accent-strong]' : 'border-gray-200 text-gray-500 hover:bg-gray-50'"
                    @click="toggleSelection('status', row.status)">
                    <span class="h-2 w-2 shrink-0 rounded-full" :style="{ backgroundColor: STATUS_COLORS[row.status] ?? '#9ca3af' }" />
                    {{ row.label }} {{ chipTotals.status[row.status] ?? 0 }}
                </button>
                <button type="button" class="px-1.5 text-xs text-gray-500 hover:text-gray-700" @click="selectedStatuses = null">{{ ctrans("All") }}</button>
                <button type="button" class="px-1.5 text-xs text-gray-500 hover:text-gray-700" @click="selectedStatuses = stats.by_status.filter((row) => !['resolved', 'cancelled'].includes(row.status)).map((row) => row.status)">{{ ctrans("Still open") }}</button>
            </div>
            <div v-if="reportTab !== 'reporters'" class="h-72">
                <Chart type="bar" :data="moduleChart" :options="moduleOptions" class="h-full" />
            </div>
            <p v-if="reportTab !== 'reporters'" class="mb-2 mt-4 text-sm font-semibold text-gray-600">{{ ctrans("By reporter") }}</p>
            <div class="-mx-4 -mb-4 overflow-x-auto">
                <table class="min-w-full text-sm tabular-nums">
                    <thead class="text-xs text-gray-500 text-left">
                        <tr>
                            <th v-for="(column, index) in reporterBreakdownColumnList" :key="column.key" class="px-4 py-2 cursor-pointer select-none hover:text-gray-700" :class="{ 'text-right': index > 0 }" @click="toggleSort('reporter_breakdown', column.key)">
                                {{ column.label }}{{ sortArrow("reporter_breakdown", column.key) }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in reporterBreakdownRows" :key="row.key" class="border-t border-gray-100">
                            <td class="px-4 py-2 font-medium">
                                <span class="inline-flex items-center gap-2">
                                    <TicketUserAvatar :name="row.name ?? '-'" :avatar="row.avatar" size="sm" />
                                    <Link :href="listUrl({ filter: reporterFilter(row.key) })" class="text-[--app-accent-strong] underline-offset-2 transition duration-200 hover:text-[--app-accent-deep] hover:underline">{{ row.name ?? "-" }}</Link>
                                    <span v-if="!row.is_staff" class="text-xs font-normal text-gray-400">{{ ctrans("Customer") }}</span>
                                </span>
                            </td>
                            <td v-for="column in reporterBreakdownColumnList.slice(1)" :key="column.key" class="px-4 py-2 text-right" :class="{ 'font-semibold': column.key === 'total' }">{{ row[column.key] || "" }}</td>
                        </tr>
                        <tr v-if="!reporterBreakdownRows.length">
                            <td :colspan="reporterBreakdownColumnList.length" class="px-4 py-6 text-center text-gray-400">{{ ctrans("No tickets in this period") }}</td>
                        </tr>
                    </tbody>
                    <tfoot v-if="reporterBreakdownRows.length" class="border-t-2 border-gray-200 font-semibold">
                        <tr>
                            <td class="px-4 py-2">{{ ctrans("Total") }}</td>
                            <td v-for="column in reporterBreakdownColumnList.slice(1)" :key="column.key" class="px-4 py-2 text-right">{{ reporterBreakdownTotals[column.key] || "" }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </DashboardWidgetBox>

        <template v-if="reportTab === 'overview'">
        <DashboardWidgetBox v-for="box in dashboardBoxes" :key="box" :storageKey="`tickets_reports_${box}_collapsed`">
            <template #header>
                <template v-if="box === 'people'">
                    <span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
                        <FontAwesomeIcon icon="fal fa-users" class="text-violet-600" fixed-width aria-hidden="true" />
                        {{ ctrans("People") }}
                    </span>
                    <span class="flex items-center gap-1.5">
                        <button
                            v-for="tab in peopleTabs"
                            :key="tab.key"
                            type="button"
                            class="rounded-full border px-2.5 py-px text-xs"
                            :class="peopleTab === tab.key ? 'border-[--app-accent] bg-[--app-accent-soft] text-[--app-accent-strong]' : 'border-gray-200 text-gray-500 hover:bg-gray-50'"
                            @click="peopleTab = tab.key as 'assignees' | 'reporters'">
                            {{ tab.label }}
                        </button>
                    </span>
                    <span v-if="peopleTab === 'assignees'" class="ml-2 inline-flex overflow-hidden rounded-full border border-gray-200 text-xs">
                        <button
                            v-for="tab in involvementTabs"
                            :key="tab.key"
                            type="button"
                            class="px-2.5 py-px transition duration-200"
                            :class="involvement === tab.key ? 'bg-[--app-accent-soft] text-[--app-accent-strong]' : 'text-gray-500 hover:bg-gray-50'"
                            @click="involvement = tab.key">
                            {{ tab.label }}
                        </button>
                    </span>
                    <span class="text-xs text-gray-400">{{ ctrans("Tickets created in this period") }}</span>
                </template>
                <template v-else>
                    <span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
                        <FontAwesomeIcon icon="fal fa-check" class="text-emerald-600" fixed-width aria-hidden="true" />
                        {{ ctrans("Cleared") }}
                    </span>
                    <span class="text-xs text-gray-400">{{ ctrans("Older tickets, created before this period, resolved in it") }}</span>
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
                            <span class="inline-flex items-center gap-2">
                                <TicketUserAvatar :name="row.name ?? '-'" :avatar="row.avatar" size="sm" />
                                {{ row.name ?? "-" }}
                                <span v-if="!row.is_staff" class="text-xs font-normal text-gray-400">{{ ctrans("Customer") }}</span>
                            </span>
                        </td>
                        <td class="px-4 py-2 text-right"><Link v-if="row.created" :href="listUrl({ filter: reporterFilter(row.key) })" class="text-[--app-accent-strong] underline-offset-2 transition duration-200 hover:text-[--app-accent-deep] hover:underline">{{ row.created }}</Link><span v-else>{{ row.created }}</span><span class="inline-block w-16 text-gray-400">{{ sharePercent(row.created, stats.assignees_total.created) }}</span></td>
                        <td class="px-4 py-2 text-right"><Link v-if="row.open" :href="listUrl({ filter: reporterFilter(row.key), elements: { status: OPEN_STATUSES } })" class="text-[--app-accent-strong] underline-offset-2 transition duration-200 hover:text-[--app-accent-deep] hover:underline">{{ row.open }}</Link><span v-else>{{ row.open }}</span></td>
                        <td class="px-4 py-2 text-right"><Link v-if="row.resolved" :href="listUrl({ filter: reporterFilter(row.key), elements: { status: 'resolved' } })" class="text-[--app-accent-strong] underline-offset-2 transition duration-200 hover:text-[--app-accent-deep] hover:underline">{{ row.resolved }}</Link><span v-else>{{ row.resolved }}</span></td>
                        <td class="px-4 py-2 text-right"><span class="inline-block w-12 pr-2 text-[9px] text-gray-400">{{ sharePercent(row.cancelled, row.created) }}</span><Link v-if="row.cancelled" :href="listUrl({ filter: reporterFilter(row.key), elements: { status: 'cancelled' } })" class="text-[--app-accent-strong] underline-offset-2 transition duration-200 hover:text-[--app-accent-deep] hover:underline">{{ row.cancelled }}</Link><span v-else>{{ row.cancelled }}</span></td>
                        <td class="px-4 py-2 text-right">{{ inDays(row.median_hours) }}</td>
                        <td class="px-4 py-2 text-right">{{ row.longest_wait_days ?? "-" }}</td>
                        <td class="px-4 py-2 text-right">
                            <Link v-if="row.rating !== null" :href="listUrl({ filter: { ...reporterFilter(row.key), rated: 1 } })" class="text-[--app-accent-strong] underline-offset-2 transition duration-200 hover:text-[--app-accent-deep] hover:underline">{{ row.rating }}<span class="text-gray-400">/5 ({{ row.ratings }})</span></Link>
                            <span v-else>-</span>
                        </td>
                    </tr>
                    <tr v-if="!stats.reporters.length">
                        <td colspan="8" class="px-4 py-6 text-center text-gray-400">{{ ctrans("No tickets in this period") }}</td>
                    </tr>
                </tbody>
                <tfoot v-if="stats.reporters.length" class="border-t-2 border-gray-200 font-semibold">
                    <tr>
                        <td class="px-4 py-2">{{ ctrans("Total") }}</td>
                        <td class="px-4 py-2 text-right"><Link v-if="stats.assignees_total.created" :href="listUrl({ filter: { created_since: stats.from } })" class="text-[--app-accent-strong] underline-offset-2 transition duration-200 hover:text-[--app-accent-deep] hover:underline">{{ stats.assignees_total.created }}</Link><span v-else>{{ stats.assignees_total.created }}</span><span class="inline-block w-16" /></td>
                        <td class="px-4 py-2 text-right"><Link v-if="stats.assignees_total.open" :href="listUrl({ filter: { created_since: stats.from }, elements: { status: OPEN_STATUSES } })" class="text-[--app-accent-strong] underline-offset-2 transition duration-200 hover:text-[--app-accent-deep] hover:underline">{{ stats.assignees_total.open }}</Link><span v-else>{{ stats.assignees_total.open }}</span></td>
                        <td class="px-4 py-2 text-right"><Link v-if="stats.assignees_total.resolved" :href="listUrl({ filter: { created_since: stats.from }, elements: { status: 'resolved' } })" class="text-[--app-accent-strong] underline-offset-2 transition duration-200 hover:text-[--app-accent-deep] hover:underline">{{ stats.assignees_total.resolved }}</Link><span v-else>{{ stats.assignees_total.resolved }}</span></td>
                        <td class="px-4 py-2 text-right"><span class="inline-block w-12 pr-2 text-[9px] text-gray-400">{{ sharePercent(stats.assignees_total.cancelled, stats.assignees_total.created) }}</span><Link v-if="stats.assignees_total.cancelled" :href="listUrl({ filter: { created_since: stats.from }, elements: { status: 'cancelled' } })" class="text-[--app-accent-strong] underline-offset-2 transition duration-200 hover:text-[--app-accent-deep] hover:underline">{{ stats.assignees_total.cancelled }}</Link><span v-else>{{ stats.assignees_total.cancelled }}</span></td>
                        <td class="px-4 py-2 text-right">{{ inDays(stats.assignees_total.median_hours) }}</td>
                        <td class="px-4 py-2 text-right">{{ stats.assignees_total.longest_wait_days ?? "-" }}</td>
                        <td class="px-4 py-2 text-right">
                            <Link v-if="stats.assignees_total.rating !== null" :href="listUrl({ filter: { created_since: stats.from, rated: 1 } })" class="text-[--app-accent-strong] underline-offset-2 transition duration-200 hover:text-[--app-accent-deep] hover:underline">{{ stats.assignees_total.rating }}<span class="text-gray-400">/5 ({{ stats.assignees_total.ratings }})</span></Link>
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
                                <TicketUserAvatar :name="row.name" :avatar="row.avatar" size="sm" />
                                {{ row.short_name }}
                            </span>
                        </td>
                        <template v-if="mode === 'assignees'">
                            <td v-for="status in ['assigned', 'in_progress'] as const" :key="status" class="px-4 py-2 text-right">
    <Link v-if="row[status]" :href="listUrl({ filter: assigneeFilter(mode, row.username, involvementFor(mode)), elements: { status: status } })" class="text-[--app-accent-strong] underline-offset-2 transition duration-200 hover:text-[--app-accent-deep] hover:underline">{{ row[status] }}</Link>
                                <span v-else>{{ row[status] }}</span>
                            </td>
                        </template>
                        <td v-if="mode === 'assignees'" class="px-4 py-2 text-right">
<Link v-if="row.open" :href="listUrl({ filter: assigneeFilter(mode, row.username, involvementFor(mode)), elements: { status: OPEN_STATUSES } })" class="text-[--app-accent-strong] underline-offset-2 transition duration-200 hover:text-[--app-accent-deep] hover:underline">{{ row.open }}</Link>
                            <span v-else>{{ row.open }}</span>
                        </td>
                        <td class="px-4 py-2 text-right">
<Link v-if="row.done" :href="listUrl({ filter: assigneeFilter(mode, row.username, involvementFor(mode)), elements: { status: 'resolved' } })" class="text-[--app-accent-strong] underline-offset-2 transition duration-200 hover:text-[--app-accent-deep] hover:underline">{{ row.done }}</Link>
                            <span v-else>{{ row.done }}</span>
                            <span class="inline-block w-16 text-gray-400">{{ sharePercent(row.done, engineerTotal(mode).done) }}</span>
                        </td>
                        <td class="px-4 py-2 text-right">{{ inDays(row.median_hours) }}</td>
                        <td v-if="mode === 'assignees'" class="px-4 py-2 text-right">{{ row.longest_wait_days ?? "-" }}</td>
                        <td class="px-4 py-2 text-right">
                            <Link v-if="row.rating !== null" :href="listUrl({ filter: { ...assigneeFilter(mode, row.username), rated: 1 } })" class="text-[--app-accent-strong] underline-offset-2 transition duration-200 hover:text-[--app-accent-deep] hover:underline">{{ row.rating }}<span class="text-gray-400">/5 ({{ row.ratings }})</span></Link>
                            <span v-else>-</span>
                        </td>
                    </tr>
                    <tr v-if="!engineerRows(mode).length">
                        <td :colspan="engineerColumns(mode).length" class="px-4 py-6 text-center text-gray-400">{{ ctrans("No tickets in this period") }}</td>
                    </tr>
                </tbody>
                <tfoot v-if="engineerRows(mode).length && involvementFor(mode) === 'assignee'" class="border-t-2 border-gray-200 font-semibold">
                    <tr>
                        <td class="px-4 py-2">{{ ctrans("Total") }}</td>
                        <td v-if="mode === 'assignees'" class="px-4 py-2 text-right"><Link v-if="engineerTotal(mode).assigned" :href="listUrl({ filter: engineerTotalFilter(mode), elements: { status: 'assigned' } })" class="text-[--app-accent-strong] underline-offset-2 transition duration-200 hover:text-[--app-accent-deep] hover:underline">{{ engineerTotal(mode).assigned }}</Link><span v-else>{{ engineerTotal(mode).assigned }}</span></td>
                        <td v-if="mode === 'assignees'" class="px-4 py-2 text-right"><Link v-if="engineerTotal(mode).in_progress" :href="listUrl({ filter: engineerTotalFilter(mode), elements: { status: 'in_progress' } })" class="text-[--app-accent-strong] underline-offset-2 transition duration-200 hover:text-[--app-accent-deep] hover:underline">{{ engineerTotal(mode).in_progress }}</Link><span v-else>{{ engineerTotal(mode).in_progress }}</span></td>
                        <td v-if="mode === 'assignees'" class="px-4 py-2 text-right"><Link v-if="engineerTotal(mode).open" :href="listUrl({ filter: engineerTotalFilter(mode), elements: { status: OPEN_STATUSES } })" class="text-[--app-accent-strong] underline-offset-2 transition duration-200 hover:text-[--app-accent-deep] hover:underline">{{ engineerTotal(mode).open }}</Link><span v-else>{{ engineerTotal(mode).open }}</span></td>
                        <td class="px-4 py-2 text-right"><Link v-if="engineerTotal(mode).done" :href="listUrl({ filter: engineerTotalFilter(mode), elements: { status: 'resolved' } })" class="text-[--app-accent-strong] underline-offset-2 transition duration-200 hover:text-[--app-accent-deep] hover:underline">{{ engineerTotal(mode).done }}</Link><span v-else>{{ engineerTotal(mode).done }}</span><span class="inline-block w-16" /></td>
                        <td class="px-4 py-2 text-right">{{ inDays(engineerTotal(mode).median_hours) }}</td>
                        <td v-if="mode === 'assignees'" class="px-4 py-2 text-right">{{ engineerTotal(mode).longest_wait_days ?? "-" }}</td>
                        <td class="px-4 py-2 text-right">
                            <Link v-if="engineerTotal(mode).rating !== null" :href="listUrl({ filter: { ...engineerTotalFilter(mode), rated: 1 } })" class="text-[--app-accent-strong] underline-offset-2 transition duration-200 hover:text-[--app-accent-deep] hover:underline">{{ engineerTotal(mode).rating }}<span class="text-gray-400">/5 ({{ engineerTotal(mode).ratings }})</span></Link>
                            <span v-else>-</span>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        </DashboardWidgetBox>
        </template>
        </div>

        <DashboardWidgetBox v-if="reportTab === 'overview'" storageKey="tickets_reports_csat_collapsed" default-collapsed>
            <template #header>
                <span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
                    <FontAwesomeIcon icon="fal fa-star" class="text-sky-600" fixed-width aria-hidden="true" />
                    {{ ctrans("Customer satisfaction") }}
                </span>
                <span class="text-xs text-gray-400">{{ ctrans("Average rating per month, last 12 months") }}</span>
            </template>
            <div class="h-56">
                <Chart type="bar" :data="csatChart" :options="csatOptions" class="h-full" />
            </div>
        </DashboardWidgetBox>
    </div>
</template>
