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
        { label: trans("Done"), data: props.stats.daily.map((day) => day.done), borderColor: "#1f845a", backgroundColor: "#1f845a", tension: 0.2 },
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
    { key: "open", label: trans("Open now") },
    { key: "done", label: trans("Done") },
    { key: "median_hours", label: trans("Median time to resolve") },
    { key: "longest_wait_days", label: trans("Longest wait") },
    { key: "rating", label: trans("Average rating") },
]

const assigneeColumns = [
    { key: "short_name", label: trans("Engineer") },
    { key: "created", label: trans("Created") },
    { key: "open", label: trans("Open") },
    { key: "done", label: trans("Done") },
    { key: "median_hours", label: trans("Median time to resolve") },
    { key: "longest_wait_days", label: trans("Longest wait") },
    { key: "rating", label: trans("Average rating") },
]

const assigneeFilter = (username: string) =>
    peopleTab.value === "resolvers" ? { assignee: username, resolved_since: props.stats.from } : { assignee: username, created_since: props.stats.from }
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <div class="p-4 space-y-4">
        <TicketsCreatedInterval :options="createdIntervals" :selected="stats.interval" />

        <div class="flex flex-wrap gap-x-10 gap-y-4">
            <div>
                <p class="text-4xl font-bold text-pink-600"><Link :href="listUrl({ filter: { created_since: stats.from } })" class="hover:underline">{{ stats.created }}</Link></p>
                <p class="text-sm text-gray-600">{{ trans("Created") }}</p>
            </div>
            <div>
                <p class="text-4xl font-bold text-green-700"><Link :href="listUrl({ filter: { resolved_since: stats.from } })" class="hover:underline">{{ stats.done }}</Link></p>
                <p class="text-sm text-gray-600">{{ trans("Done") }}</p>
            </div>
            <div>
                <p class="text-4xl font-bold"><Link :href="listUrl({ elements: { status: OPEN_STATUSES } })" class="hover:underline">{{ stats.open }}</Link></p>
                <p class="text-sm text-gray-600">{{ trans("Open now") }}</p>
            </div>
            <div>
                <p class="text-4xl font-bold"><Link :href="listUrl({ filter: { resolved_since: stats.from } })" class="hover:underline">{{ hours(stats.median_hours) }}</Link></p>
                <p class="text-sm text-gray-600">{{ trans("Median time to resolve") }}</p>
            </div>
            <div>
                <p class="text-4xl font-bold">
                    <Link v-if="stats.csat" :href="listUrl({ filter: { rated_since: stats.from } })" class="hover:underline">{{ stats.csat }}</Link>
                    <span v-else>-</span>
                    <span v-if="stats.csat" class="text-lg text-gray-400">/5</span>
                </p>
                <p class="text-sm text-gray-600">{{ trans("Customer satisfaction") }}</p>
            </div>
            <div v-if="stats.oldest_open">
                <p class="text-4xl font-bold">
                    <Link :href="route('grp.tickets.show', stats.oldest_open.reference)" class="hover:underline">{{ stats.oldest_open.age_days }}</Link>
                    <span class="text-lg text-gray-400"> {{ trans("days") }}</span>
                </p>
                <p class="text-sm text-gray-600">
                    {{ trans("Oldest open") }} · <Link :href="route('grp.tickets.show', stats.oldest_open.reference)" class="hover:underline">{{ stats.oldest_open.reference }}</Link>
                </p>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <div class="bg-white rounded-lg p-4 shadow-sm border border-gray-300 lg:col-span-2">
                <h3 class="text-lg font-semibold mb-2">{{ trans("Created vs Done") }}</h3>
                <div class="h-72">
                    <Chart type="line" :data="lineChart" :options="lineOptions" class="h-full" />
                </div>
            </div>
            <div class="bg-white rounded-lg p-4 shadow-sm border border-gray-300">
                <h3 class="text-lg font-semibold">{{ trans("Status overview") }}</h3>
                <p class="text-xs text-gray-500 mb-2">{{ trans("All tickets") }} · <Link :href="route('grp.tickets.list')" class="hover:underline">{{ trans("View all") }}</Link></p>
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
            </div>
        </div>

        <div class="bg-white rounded-lg p-4 shadow-sm border border-gray-300">
            <h3 class="text-lg font-semibold">{{ trans("Customer satisfaction") }}</h3>
            <p class="text-xs text-gray-500 mb-2">{{ trans("Average rating per month, last 12 months") }}</p>
            <div class="h-56">
                <Chart type="bar" :data="csatChart" :options="csatOptions" class="h-full" />
            </div>
        </div>

        <div class="flex items-end gap-1 border-b border-gray-200">
            <button
                v-for="tab in peopleTabs"
                :key="tab.key"
                type="button"
                class="-mb-px border-b-2 px-4 py-2 text-sm"
                :class="peopleTab === tab.key ? 'border-indigo-600 font-medium text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                @click="peopleTab = tab.key as 'assignees' | 'resolvers' | 'reporters'">
                {{ tab.label }}
            </button>
            <p class="ml-auto pb-2 text-xs text-gray-500">
                {{ peopleTab === "resolvers" ? trans("Tickets resolved in this period, created at any time") : trans("Tickets created in this period") }}
            </p>
        </div>

        <div v-if="peopleTab === 'reporters'" class="bg-white rounded-lg shadow-sm border border-gray-300 overflow-x-auto">
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

        <div v-else class="bg-white rounded-lg shadow-sm border border-gray-300 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-xs text-gray-500 text-left">
                    <tr>
                        <th v-for="(column, index) in assigneeColumns" :key="column.key" class="px-4 py-2 cursor-pointer select-none hover:text-gray-700" :class="{ 'text-right': index > 0 }" @click="toggleSort(column.key)">
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
                        <td class="px-4 py-2 text-right">
                            <Link v-if="row.created" :href="listUrl({ filter: assigneeFilter(row.username) })" class="hover:underline">{{ row.created }}</Link>
                            <span v-else>{{ row.created }}</span>
                        </td>
                        <td class="px-4 py-2 text-right">
                            <Link v-if="row.open" :href="listUrl({ filter: assigneeFilter(row.username), elements: { status: OPEN_STATUSES } })" class="hover:underline">{{ row.open }}</Link>
                            <span v-else>{{ row.open }}</span>
                        </td>
                        <td class="px-4 py-2 text-right">
                            <Link v-if="row.done" :href="listUrl({ filter: assigneeFilter(row.username), elements: { status: 'resolved' } })" class="hover:underline">{{ row.done }}</Link>
                            <span v-else>{{ row.done }}</span>
                        </td>
                        <td class="px-4 py-2 text-right">{{ hours(row.median_hours) }}</td>
                        <td class="px-4 py-2 text-right">{{ days(row.longest_wait_days) }}</td>
                        <td class="px-4 py-2 text-right">
                            <template v-if="row.rating !== null">{{ row.rating }}<span class="text-gray-400">/5 ({{ row.ratings }})</span></template>
                            <span v-else>-</span>
                        </td>
                    </tr>
                    <tr v-if="!sortedAssignees.length">
                        <td colspan="7" class="px-4 py-6 text-center text-gray-400">{{ trans("No tickets in this period") }}</td>
                    </tr>
                </tbody>
                <tfoot v-if="sortedAssignees.length" class="border-t-2 border-gray-200 font-semibold">
                    <tr>
                        <td class="px-4 py-2">{{ trans("Total") }}</td>
                        <td class="px-4 py-2 text-right">{{ assigneeTotal.created }}</td>
                        <td class="px-4 py-2 text-right">{{ assigneeTotal.open }}</td>
                        <td class="px-4 py-2 text-right">{{ assigneeTotal.done }}</td>
                        <td class="px-4 py-2 text-right">{{ hours(assigneeTotal.median_hours) }}</td>
                        <td class="px-4 py-2 text-right">{{ days(assigneeTotal.longest_wait_days) }}</td>
                        <td class="px-4 py-2 text-right">
                            <template v-if="assigneeTotal.rating !== null">{{ assigneeTotal.rating }}<span class="text-gray-400">/5 ({{ assigneeTotal.ratings }})</span></template>
                            <span v-else>-</span>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</template>
