<script setup lang="ts">
import { router } from "@inertiajs/vue3"
import { computed, onMounted, ref } from "vue"
import { Bar, Line } from "vue-chartjs"
import { BarElement, CategoryScale, Chart as ChartJS, Filler, Legend, LinearScale, LineElement, PointElement, Tooltip } from "chart.js"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { ctrans } from "@/Composables/useTrans"
import SqlCode from "@/Components/DevOps/SqlCode.vue"
import TraceWaterfall, { formatMicroseconds, Trace } from "@/Components/DevOps/TraceWaterfall.vue"
import ExceptionDetail, { ExceptionDetailData, ExceptionOccurrence } from "@/Components/DevOps/ExceptionDetail.vue"

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, BarElement, Tooltip, Legend, Filler)

type SeriesPoint = { bucket_start: string, calls: number, avg: number, p95: number, max_duration: number, [count: string]: number | string }
type TopRow = { group_hash: string, label: string, calls: number, total_duration: number, avg: number, p95: number, max_duration: number, [extra: string]: number | string | null }
type SlowRequest = { id: number, created_at: string, url: string, status_code: number, duration: number, queries: number, cache_events: number, outgoing_requests: number, route_name: string | null, method: string | null }
type SlowJob = { id: number, created_at: string, job_class: string | null, queue: string | null, status: string | null, attempt: number, duration: number, queries: number, cache_events: number, outgoing_requests: number }
type SlowCommand = { id: number, created_at: string, name: string, command: string | null, exit_code: number | null, duration: number, queries: number, cache_events: number, outgoing_requests: number }
type ExceptionGroup = { fingerprint: string, occurrences: number, handled: number, unhandled: number, last_bucket: string, exception_class: string | null, exception_message: string | null, status: string | null, priority: string | null, last_seen_at: string | null, users_count: number | null }

export type Telemetry = {
    range: string
    dataSince: string | null
    requests: SeriesPoint[]
    jobs: SeriesPoint[]
    exceptions: { bucket_start: string, handled: number, unhandled: number }[]
    routes: TopRow[]
    jobClasses: TopRow[]
    commands: TopRow[]
    scheduledTasks: TopRow[]
    queries: TopRow[]
    outgoing: TopRow[]
    cache: { hits: number, misses: number, writes: number, fails: number }
    exceptionGroups: ExceptionGroup[]
    slowRequests: SlowRequest[]
    slowJobs: SlowJob[]
    slowCommands: SlowCommand[]
}

const props = defineProps<{ telemetry?: Telemetry | null, trace?: Trace | null, exception?: ExceptionDetailData | null }>()

const loading = ref(false)
const load = (range: string) => {
    loading.value = true
    router.reload({ only: ["telemetry"], data: { range }, onFinish: () => loading.value = false })
}
onMounted(() => !props.telemetry && load(new URLSearchParams(window.location.search).get("range") ?? "24h"))

const exceptionLoading = ref(false)
const isExceptionOpen = ref(Boolean(props.exception))
const openException = (fingerprint: string, occurrence?: ExceptionOccurrence) => {
    exceptionLoading.value = true
    router.reload({ only: ["telemetryException"], data: { exception: fingerprint, occurrence: occurrence?.id ?? null, occurrence_at: occurrence?.created_at ?? null }, onFinish: () => {
        exceptionLoading.value = false
        isExceptionOpen.value = true
    } })
}

const traceLoading = ref(false)
const isTraceOpen = ref(Boolean(props.trace))
const openTrace = (kind: Trace["summary"]["kind"], row: { id: number, created_at: string }) => {
    traceLoading.value = true
    router.reload({ only: ["telemetryTrace"], data: { trace: kind, id: row.id, at: row.created_at }, onFinish: () => {
        traceLoading.value = false
        isTraceOpen.value = true
    } })
}

const ranges = computed(() => ({ "1h": ctrans("Last hour"), "24h": ctrans("Last 24 hours"), "7d": ctrans("Last 7 days") }))
const sum = (points: Record<string, unknown>[], key: string) => points.reduce((total, point) => total + Number(point[key] ?? 0), 0)
const compact = (value: number) => Intl.NumberFormat(undefined, { notation: "compact", maximumFractionDigits: 1 }).format(value)

const label = (bucket: string) => {
    const date = new Date(bucket.replace(" ", "T") + "Z")
    return props.telemetry?.range === "7d" ? date.toLocaleString(undefined, { weekday: "short", hour: "2-digit", minute: "2-digit" }) : date.toLocaleTimeString(undefined, { hour: "2-digit", minute: "2-digit" })
}
const bars = (points: Record<string, unknown>[], series: [string, string, string][]) => ({
    labels: points.map(point => label(String(point.bucket_start))),
    datasets: series.map(([key, title, colour]) => ({ label: title, data: points.map(point => Number(point[key] ?? 0)), backgroundColor: colour, stack: "total", borderRadius: 1 })),
})
const durations = (points: SeriesPoint[]) => ({
    labels: points.map(point => label(point.bucket_start)),
    datasets: [
        { label: ctrans("Average"), data: points.map(point => point.avg / 1000), borderColor: "#94a3b8", backgroundColor: "#94a3b8", borderWidth: 1.5, pointRadius: 0, tension: 0.2 },
        { label: "P95", data: points.map(point => point.p95 / 1000), borderColor: "#f59e0b", backgroundColor: "rgba(245, 158, 11, 0.1)", fill: true, borderWidth: 1.5, pointRadius: 0, tension: 0.2 },
    ],
})
const barOptions = { responsive: true, maintainAspectRatio: false, animation: false as const, plugins: { legend: { display: false } }, scales: { x: { stacked: true, ticks: { maxTicksLimit: 8 }, grid: { display: false } }, y: { stacked: true, beginAtZero: true } } }
const lineOptions = { responsive: true, maintainAspectRatio: false, animation: false as const, interaction: { mode: "index" as const, intersect: false }, plugins: { legend: { display: false }, tooltip: { callbacks: { label: (item: { dataset: { label?: string }, parsed: { y: number } }) => `${item.dataset.label}: ${formatMicroseconds(item.parsed.y * 1000)}` } } }, scales: { x: { ticks: { maxTicksLimit: 8 }, grid: { display: false } }, y: { beginAtZero: true, ticks: { callback: (value: string | number) => `${value} ms` } } } }

const cards = computed(() => {
    const telemetry = props.telemetry
    if (!telemetry) {
        return []
    }
    return [
        {
            title: ctrans("Requests"), total: sum(telemetry.requests, "calls"), chart: bars(telemetry.requests, [["ok", "1/2/3xx", "#94a3b8"], ["client_errors", "4xx", "#f59e0b"], ["server_errors", "5xx", "#e11d48"]]),
            legend: [["1/2/3xx", sum(telemetry.requests, "ok"), "bg-slate-400"], ["4xx", sum(telemetry.requests, "client_errors"), "bg-amber-500"], ["5xx", sum(telemetry.requests, "server_errors"), "bg-rose-600"]],
        },
        {
            title: ctrans("Exceptions"), total: sum(telemetry.exceptions, "handled") + sum(telemetry.exceptions, "unhandled"), chart: bars(telemetry.exceptions, [["handled", ctrans("Handled"), "#10b981"], ["unhandled", ctrans("Unhandled"), "#e11d48"]]),
            legend: [[ctrans("Handled"), sum(telemetry.exceptions, "handled"), "bg-emerald-500"], [ctrans("Unhandled"), sum(telemetry.exceptions, "unhandled"), "bg-rose-600"]],
        },
        {
            title: ctrans("Jobs"), total: sum(telemetry.jobs, "calls"), chart: bars(telemetry.jobs, [["processed", ctrans("Processed"), "#94a3b8"], ["released", ctrans("Released"), "#f59e0b"], ["failed", ctrans("Failed"), "#e11d48"]]),
            legend: [[ctrans("Processed"), sum(telemetry.jobs, "processed"), "bg-slate-400"], [ctrans("Released"), sum(telemetry.jobs, "released"), "bg-amber-500"], [ctrans("Failed"), sum(telemetry.jobs, "failed"), "bg-rose-600"]],
        },
    ]
})

const durationCards = computed(() => {
    const telemetry = props.telemetry
    if (!telemetry) {
        return []
    }
    const summary = (points: SeriesPoint[]) => {
        const calls = sum(points, "calls")
        return { avg: calls ? sum(points, "total_duration") / calls : 0, p95: Math.max(0, ...points.map(point => point.p95)) }
    }
    return [
        { title: ctrans("Request duration"), chart: durations(telemetry.requests), ...summary(telemetry.requests) },
        { title: ctrans("Job duration"), chart: durations(telemetry.jobs), ...summary(telemetry.jobs) },
    ]
})

type TableKey = "slowRequests" | "slowJobs" | "slowCommands" | "routes" | "queries" | "jobClasses" | "commands" | "scheduledTasks" | "outgoing" | "exceptionGroups"
const tables = computed<Record<TableKey, string>>(() => ({
    slowRequests: ctrans("Slowest requests (last hour)"),
    slowJobs: ctrans("Slowest jobs (last hour)"),
    slowCommands: ctrans("Slowest commands (last day)"),
    routes: ctrans("Routes"),
    queries: ctrans("Queries"),
    jobClasses: ctrans("Jobs"),
    commands: ctrans("Commands"),
    scheduledTasks: ctrans("Scheduled tasks"),
    outgoing: ctrans("Outgoing HTTP"),
    exceptionGroups: ctrans("Exceptions"),
}))
const currentTable = ref<TableKey>("slowRequests")
const sortKey = ref<"total_duration" | "calls" | "p95" | "max_duration">("total_duration")
const sortKeys = computed(() => ({ total_duration: ctrans("Total time"), calls: ctrans("Count"), p95: "P95", max_duration: ctrans("Slowest") }))
const errorColumns: Partial<Record<TableKey, [string, string][]>> = {
    routes: [["client_errors", "4xx"], ["server_errors", "5xx"]],
    jobClasses: [["released", "Released"], ["failed", "Failed"]],
    commands: [["failed", "Failed"]],
    scheduledTasks: [["skipped", "Skipped"], ["failed", "Failed"]],
    outgoing: [["client_errors", "4xx"], ["server_errors", "5xx"]],
}
const isListTable = computed(() => ["slowRequests", "slowJobs", "slowCommands", "exceptionGroups"].includes(currentTable.value))
const rows = computed(() => {
    const key = currentTable.value
    if (!props.telemetry || isListTable.value) {
        return []
    }
    return [...props.telemetry[key]].sort((a, b) => Number(b[sortKey.value]) - Number(a[sortKey.value])).slice(0, 25)
})
const rowLabel = (row: TopRow) => currentTable.value === "routes" ? row.label.replace(/^\["?([^"\]]+)"?(?:,"[^"]+")*\]/, "$1") : row.label
const openedQuery = ref<string | null>(null)

const cacheHitRate = computed(() => {
    const cache = props.telemetry?.cache
    const reads = Number(cache?.hits ?? 0) + Number(cache?.misses ?? 0)
    return reads ? Math.round(Number(cache?.hits) / reads * 100) : null
})
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center gap-2">
            <button v-for="(title, range) in ranges" :key="range" class="rounded border px-3 py-1 text-sm"
                :class="telemetry?.range === range ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'bg-white text-gray-600 hover:bg-gray-50'" @click="load(range)">
                {{ title }}
            </button>
            <span v-if="loading" class="text-sm text-gray-500"><FontAwesomeIcon icon="fal fa-spinner-third" class="animate-spin" fixed-width aria-hidden="true" /> {{ ctrans("Loading") }}</span>
            <span v-if="telemetry?.dataSince" class="ml-auto text-xs text-gray-500">{{ ctrans("Data since") }} {{ telemetry.dataSince }} UTC</span>
        </div>

        <div v-if="!telemetry" class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div v-for="index in 3" :key="index" class="h-56 animate-pulse rounded border bg-gray-100" />
        </div>

        <template v-else>
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div v-for="card in cards" :key="card.title" class="rounded border bg-white p-4">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ card.title }}</div>
                            <div class="font-mono text-xl">{{ compact(card.total) }}</div>
                        </div>
                        <div class="flex gap-3 text-right text-xs">
                            <div v-for="[title, value, colour] in card.legend" :key="String(title)">
                                <div class="flex items-center justify-end gap-1 text-gray-500"><span class="inline-block h-2 w-2 rounded-sm" :class="colour" />{{ title }}</div>
                                <div class="font-mono text-sm">{{ compact(Number(value)) }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-2 h-40"><Bar :data="card.chart" :options="barOptions" /></div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div v-for="card in durationCards" :key="card.title" class="rounded border bg-white p-4">
                    <div class="flex items-start justify-between">
                        <div class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ card.title }}</div>
                        <div class="flex gap-3 text-right text-xs">
                            <div><div class="text-gray-500">{{ ctrans("Average") }}</div><div class="font-mono text-sm">{{ formatMicroseconds(card.avg) }}</div></div>
                            <div><div class="text-amber-600">{{ ctrans("Worst P95") }}</div><div class="font-mono text-sm">{{ formatMicroseconds(card.p95) }}</div></div>
                        </div>
                    </div>
                    <div class="mt-2 h-40"><Line :data="card.chart" :options="lineOptions" /></div>
                </div>
                <div class="rounded border bg-white p-4">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ ctrans("Cache") }}</div>
                    <div class="font-mono text-xl">{{ cacheHitRate === null ? "—" : `${cacheHitRate}%` }} <span class="text-xs text-gray-500">{{ ctrans("hit rate") }}</span></div>
                    <dl class="mt-3 grid grid-cols-2 gap-2 text-sm">
                        <div><dt class="text-xs text-gray-500">{{ ctrans("Hits") }}</dt><dd class="font-mono">{{ compact(Number(telemetry.cache?.hits ?? 0)) }}</dd></div>
                        <div><dt class="text-xs text-gray-500">{{ ctrans("Misses") }}</dt><dd class="font-mono">{{ compact(Number(telemetry.cache?.misses ?? 0)) }}</dd></div>
                        <div><dt class="text-xs text-gray-500">{{ ctrans("Writes") }}</dt><dd class="font-mono">{{ compact(Number(telemetry.cache?.writes ?? 0)) }}</dd></div>
                        <div><dt class="text-xs text-gray-500">{{ ctrans("Failures") }}</dt><dd class="font-mono" :class="Number(telemetry.cache?.fails) ? 'text-rose-600' : ''">{{ compact(Number(telemetry.cache?.fails ?? 0)) }}</dd></div>
                    </dl>
                </div>
            </div>

            <ExceptionDetail v-if="exception && isExceptionOpen" :exception="exception" @close="isExceptionOpen = false"
                @open-occurrence="occurrence => openException(exception!.fingerprint, occurrence)" @open-trace="openTrace" />
            <TraceWaterfall v-if="trace && isTraceOpen" :trace="trace" @close="isTraceOpen = false" />

            <div class="rounded border bg-white">
                <div class="flex flex-wrap items-center gap-1 border-b px-2 pt-2">
                    <button v-for="(title, key) in tables" :key="key" class="-mb-px border-b-2 px-3 py-2 text-sm"
                        :class="currentTable === key ? 'border-indigo-500 text-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-800'" @click="currentTable = key">
                        {{ title }}
                    </button>
                    <div v-if="!isListTable" class="ml-auto flex items-center gap-1 pb-2 text-xs text-gray-500">
                        {{ ctrans("Sort by") }}
                        <button v-for="(title, key) in sortKeys" :key="key" class="rounded px-2 py-1" :class="sortKey === key ? 'bg-gray-800 text-white' : 'hover:bg-gray-100'" @click="sortKey = key">{{ title }}</button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table v-if="currentTable === 'slowRequests'" class="min-w-full text-sm">
                        <thead class="text-left text-xs text-gray-500"><tr>
                            <th class="px-3 py-2">{{ ctrans("Request") }}</th><th class="px-3 py-2">{{ ctrans("Status") }}</th>
                            <th class="px-3 py-2 text-right">{{ ctrans("Queries") }}</th><th class="px-3 py-2 text-right">{{ ctrans("Cache") }}</th><th class="px-3 py-2 text-right">{{ ctrans("HTTP") }}</th>
                            <th class="px-3 py-2 text-right">{{ ctrans("Duration") }}</th><th class="px-3 py-2">{{ ctrans("At (UTC)") }}</th>
                        </tr></thead>
                        <tbody>
                            <tr v-for="request in telemetry.slowRequests" :key="request.id" class="cursor-pointer border-t hover:bg-indigo-50" @click="openTrace('request', request)">
                                <td class="max-w-xl truncate px-3 py-1.5 font-mono text-xs" :title="request.url"><span class="font-semibold">{{ request.method }}</span> {{ request.url }}</td>
                                <td class="px-3 py-1.5" :class="request.status_code >= 500 ? 'text-rose-600' : request.status_code >= 400 ? 'text-amber-600' : ''">{{ request.status_code }}</td>
                                <td class="px-3 py-1.5 text-right tabular-nums">{{ request.queries }}</td>
                                <td class="px-3 py-1.5 text-right tabular-nums">{{ request.cache_events }}</td>
                                <td class="px-3 py-1.5 text-right tabular-nums">{{ request.outgoing_requests }}</td>
                                <td class="px-3 py-1.5 text-right font-mono">{{ formatMicroseconds(request.duration) }}</td>
                                <td class="whitespace-nowrap px-3 py-1.5 text-xs text-gray-500">{{ request.created_at }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <table v-else-if="currentTable === 'slowJobs' || currentTable === 'slowCommands'" class="min-w-full text-sm">
                        <thead class="text-left text-xs text-gray-500"><tr>
                            <th class="px-3 py-2">{{ currentTable === 'slowJobs' ? ctrans("Job") : ctrans("Command") }}</th><th class="px-3 py-2">{{ ctrans("Status") }}</th>
                            <th class="px-3 py-2 text-right">{{ ctrans("Queries") }}</th><th class="px-3 py-2 text-right">{{ ctrans("Cache") }}</th><th class="px-3 py-2 text-right">{{ ctrans("HTTP") }}</th>
                            <th class="px-3 py-2 text-right">{{ ctrans("Duration") }}</th><th class="px-3 py-2">{{ ctrans("At (UTC)") }}</th>
                        </tr></thead>
                        <tbody>
                            <template v-if="currentTable === 'slowJobs'">
                                <tr v-for="job in telemetry.slowJobs" :key="job.id" class="cursor-pointer border-t hover:bg-indigo-50" @click="openTrace('job', job)">
                                    <td class="max-w-xl px-3 py-1.5"><div class="truncate font-mono text-xs" :title="job.job_class ?? ''">{{ job.job_class }}</div><div class="text-xs text-gray-400">{{ job.queue }}<template v-if="job.attempt > 1"> · {{ ctrans("attempt") }} {{ job.attempt }}</template></div></td>
                                    <td class="px-3 py-1.5 text-xs" :class="job.status === 'failed' ? 'text-rose-600' : job.status === 'released' ? 'text-amber-600' : ''">{{ job.status }}</td>
                                    <td class="px-3 py-1.5 text-right tabular-nums">{{ compact(job.queries) }}</td>
                                    <td class="px-3 py-1.5 text-right tabular-nums">{{ compact(job.cache_events) }}</td>
                                    <td class="px-3 py-1.5 text-right tabular-nums">{{ compact(job.outgoing_requests) }}</td>
                                    <td class="px-3 py-1.5 text-right font-mono">{{ formatMicroseconds(job.duration) }}</td>
                                    <td class="whitespace-nowrap px-3 py-1.5 text-xs text-gray-500">{{ job.created_at }}</td>
                                </tr>
                            </template>
                            <template v-else>
                                <tr v-for="command in telemetry.slowCommands" :key="command.id" class="cursor-pointer border-t hover:bg-indigo-50" @click="openTrace('command', command)">
                                    <td class="max-w-xl truncate px-3 py-1.5 font-mono text-xs" :title="command.command ?? command.name">{{ command.command || command.name }}</td>
                                    <td class="px-3 py-1.5 text-xs" :class="command.exit_code ? 'text-rose-600' : ''">{{ ctrans("exit") }} {{ command.exit_code }}</td>
                                    <td class="px-3 py-1.5 text-right tabular-nums">{{ compact(command.queries) }}</td>
                                    <td class="px-3 py-1.5 text-right tabular-nums">{{ compact(command.cache_events) }}</td>
                                    <td class="px-3 py-1.5 text-right tabular-nums">{{ compact(command.outgoing_requests) }}</td>
                                    <td class="px-3 py-1.5 text-right font-mono">{{ formatMicroseconds(command.duration) }}</td>
                                    <td class="whitespace-nowrap px-3 py-1.5 text-xs text-gray-500">{{ command.created_at }}</td>
                                </tr>
                            </template>
                        </tbody>
                    </table>

                    <table v-else-if="currentTable === 'exceptionGroups'" class="min-w-full text-sm">
                        <thead class="text-left text-xs text-gray-500"><tr>
                            <th class="px-3 py-2">{{ ctrans("Exception") }}</th><th class="px-3 py-2">{{ ctrans("Status") }}</th>
                            <th class="px-3 py-2 text-right">{{ ctrans("Occurrences") }}</th><th class="px-3 py-2 text-right">{{ ctrans("Handled") }}</th><th class="px-3 py-2 text-right">{{ ctrans("Unhandled") }}</th>
                            <th class="px-3 py-2 text-right">{{ ctrans("Users") }}</th><th class="px-3 py-2">{{ ctrans("Last seen (UTC)") }}</th>
                        </tr></thead>
                        <tbody>
                            <tr v-for="group in telemetry.exceptionGroups" :key="group.fingerprint" class="cursor-pointer border-t align-top hover:bg-indigo-50" @click="openException(group.fingerprint)">
                                <td class="max-w-2xl px-3 py-1.5"><div class="font-mono text-xs font-semibold">{{ group.exception_class ?? group.fingerprint }}</div><div class="line-clamp-2 text-xs text-gray-600">{{ group.exception_message }}</div></td>
                                <td class="px-3 py-1.5 text-xs">{{ group.status }}<template v-if="group.priority"> · {{ group.priority }}</template></td>
                                <td class="px-3 py-1.5 text-right tabular-nums">{{ compact(Number(group.occurrences)) }}</td>
                                <td class="px-3 py-1.5 text-right tabular-nums text-gray-500">{{ compact(Number(group.handled)) }}</td>
                                <td class="px-3 py-1.5 text-right tabular-nums" :class="Number(group.unhandled) ? 'text-rose-600' : 'text-gray-400'">{{ compact(Number(group.unhandled)) }}</td>
                                <td class="px-3 py-1.5 text-right tabular-nums">{{ group.users_count }}</td>
                                <td class="whitespace-nowrap px-3 py-1.5 text-xs text-gray-500">{{ group.last_seen_at ?? group.last_bucket }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <table v-else class="min-w-full text-sm">
                        <thead class="text-left text-xs text-gray-500"><tr>
                            <th class="px-3 py-2">{{ tables[currentTable] }}</th>
                            <th class="px-3 py-2 text-right">{{ ctrans("Count") }}</th>
                            <th v-for="[key, title] in errorColumns[currentTable] ?? []" :key="key" class="px-3 py-2 text-right">{{ ctrans(title) }}</th>
                            <th class="px-3 py-2 text-right">{{ ctrans("Average") }}</th><th class="px-3 py-2 text-right">P95</th>
                            <th class="px-3 py-2 text-right">{{ ctrans("Slowest") }}</th><th class="px-3 py-2 text-right">{{ ctrans("Total time") }}</th>
                        </tr></thead>
                        <tbody>
                            <template v-for="row in rows" :key="row.group_hash">
                                <tr class="border-t align-top" :class="currentTable === 'queries' ? 'cursor-pointer hover:bg-indigo-50' : ''" @click="currentTable === 'queries' && (openedQuery = openedQuery === row.group_hash ? null : row.group_hash)">
                                    <td class="max-w-2xl px-3 py-1.5">
                                        <SqlCode v-if="currentTable === 'queries'" :sql="row.label" :compact="openedQuery !== row.group_hash" />
                                        <div v-else class="truncate font-mono text-xs" :title="row.label">{{ rowLabel(row) }}</div>
                                        <div v-if="row.queue || row.expression || row.connection" class="text-xs text-gray-400">{{ row.queue ?? row.expression ?? row.connection }}</div>
                                    </td>
                                    <td class="px-3 py-1.5 text-right tabular-nums">{{ compact(row.calls) }}</td>
                                    <td v-for="[key] in errorColumns[currentTable] ?? []" :key="key" class="px-3 py-1.5 text-right tabular-nums" :class="Number(row[key]) ? 'text-rose-600' : 'text-gray-400'">{{ compact(Number(row[key] ?? 0)) }}</td>
                                    <td class="px-3 py-1.5 text-right font-mono">{{ formatMicroseconds(row.avg) }}</td>
                                    <td class="px-3 py-1.5 text-right font-mono">{{ formatMicroseconds(row.p95) }}</td>
                                    <td class="px-3 py-1.5 text-right font-mono">{{ formatMicroseconds(row.max_duration) }}</td>
                                    <td class="px-3 py-1.5 text-right font-mono">{{ formatMicroseconds(row.total_duration) }}</td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    <div v-if="traceLoading || exceptionLoading" class="border-t px-3 py-2 text-sm text-gray-500">{{ ctrans("Loading") }}…</div>
                </div>
            </div>
        </template>
    </div>
</template>
