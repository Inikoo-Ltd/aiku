<script setup lang="ts">
import { computed } from "vue"
import { Line } from "vue-chartjs"
import { Chart as ChartJS, LineElement, PointElement, CategoryScale, LinearScale, Tooltip, Legend, Filler } from "chart.js"
import { ctrans } from "@/Composables/useTrans"

ChartJS.register(LineElement, PointElement, CategoryScale, LinearScale, Tooltip, Legend, Filler)

export interface CodeHealthData {
    compare_days: number
    coverage: {
        measured_at: string
        head_sha: string | null
        coverage: number | null
        lines: number
        hits: number
        change: number | null
        modules: { module: string, lines: number, hits: number, coverage: number | null, change: number | null }[]
    } | null
    coverage_history: { day: string, source: "codecov" | "aiku", coverage: number | null }[]
    bundles: {
        measured_at: string
        head_sha: string | null
        bytes: number
        gzip_bytes: number
        change: number | null
        bundles: { name: string, files: number, js_bytes: number, css_bytes: number, gzip_bytes: number, change: number | null, largest: { file: string, bytes: number, gzip_bytes: number }[] }[]
    } | null
    bundle_history: { day: string, bytes: number, gzip_bytes: number }[]
}

const props = defineProps<{ health: CodeHealthData }>()

const formatBytes = (bytes: number) => bytes >= 1048576 ? `${(bytes / 1048576).toFixed(2)} MB` : `${Math.round(bytes / 1024).toLocaleString()} KB`
const isFlatCoverage = (change: number) => Math.abs(change) < 0.05
const isFlatSize = (change: number) => Math.abs(change) < 1024
const signed = (value: number, text: string, isFlat: boolean) => isFlat ? "0" : `${value > 0 ? "+" : "−"}${text}`
const formatPercentage = (coverage: number | null) => coverage === null ? "-" : `${coverage}%`
const formatDay = (day: string) => new Date(`${day}T00:00:00`).toLocaleDateString([], { day: "numeric", month: "short" })
const formatDate = (iso: string) => new Date(iso).toLocaleDateString([], { day: "numeric", month: "short", year: "numeric" })

const coverageClass = (coverage: number | null) => coverage === null ? "text-gray-400" : coverage >= 70 ? "text-green-700" : coverage >= 40 ? "text-amber-700" : "text-red-700"
const coverageBarClass = (coverage: number | null) => coverage === null ? "bg-gray-200" : coverage >= 70 ? "bg-green-500" : coverage >= 40 ? "bg-amber-500" : "bg-red-500"
const coverageChangeClass = (change: number | null) => change === null || isFlatCoverage(change) ? "text-gray-400" : change > 0 ? "text-green-700" : "text-red-700"
const sizeChangeClass = (change: number | null) => change === null || isFlatSize(change) ? "text-gray-400" : change < 0 ? "text-green-700" : "text-red-700"

const options = (suffix: string, beginAtZero: boolean) => ({
    responsive: true,
    maintainAspectRatio: false,
    animation: false as const,
    interaction: { mode: "index" as const, intersect: false },
    plugins: { legend: { position: "bottom" as const } },
    scales: { y: { beginAtZero, ticks: { callback: (value: string | number) => `${value}${suffix}` } } },
})

const coverageChart = computed(() => ({
    labels: props.health.coverage_history.map(day => formatDay(day.day)),
    datasets: [
        { label: ctrans("Counted by Codecov"), data: props.health.coverage_history.map(day => day.source === "codecov" ? day.coverage : null), borderColor: "#9ca3af", borderDash: [4, 4], pointRadius: 0, tension: 0.2 },
        { label: ctrans("Counted by aiku"), data: props.health.coverage_history.map(day => day.source === "aiku" ? day.coverage : null), borderColor: "#10b981", backgroundColor: "rgba(16, 185, 129, 0.1)", fill: true, pointRadius: 2, tension: 0.2 },
    ].filter(dataset => dataset.data.some(value => value !== null)),
}))

const firstAikuDay = computed(() => props.health.coverage_history.find(day => day.source === "aiku")?.day ?? null)
const hasCodecovHistory = computed(() => props.health.coverage_history.some(day => day.source === "codecov"))

const bundleChart = computed(() => ({
    labels: props.health.bundle_history.map(day => formatDay(day.day)),
    datasets: [
        { label: ctrans("Total"), data: props.health.bundle_history.map(day => +(day.bytes / 1048576).toFixed(2)), borderColor: "#6366f1", pointRadius: 0, tension: 0.2 },
        { label: ctrans("Gzipped"), data: props.health.bundle_history.map(day => +(day.gzip_bytes / 1048576).toFixed(2)), borderColor: "#f59e0b", pointRadius: 0, tension: 0.2 },
    ],
}))
</script>

<template>
    <div class="space-y-4">
        <section class="rounded-lg border border-gray-200 p-4">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <span class="text-sm font-medium">{{ ctrans("Test coverage") }}</span>
                <span v-if="health.coverage" class="text-xs text-gray-500">{{ ctrans("Nightly run of :date", { date: formatDate(health.coverage.measured_at) }) }}<template v-if="health.coverage.head_sha"> · {{ health.coverage.head_sha }}</template></span>
            </div>
            <div v-if="!health.coverage" class="mt-3 text-sm text-gray-500">{{ ctrans("No coverage reported yet. The nightly coverage run sends it.") }}</div>
            <template v-else>
                <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <div class="rounded bg-gray-50 p-3">
                        <div class="text-xs text-gray-500">{{ ctrans("Line coverage") }}</div>
                        <div class="mt-1 text-lg font-semibold tabular-nums" :class="coverageClass(health.coverage.coverage)">{{ formatPercentage(health.coverage.coverage) }}</div>
                    </div>
                    <div class="rounded bg-gray-50 p-3">
                        <div class="text-xs text-gray-500">{{ ctrans("Change in :days days", { days: String(health.compare_days) }) }}</div>
                        <div class="mt-1 text-lg font-semibold tabular-nums" :class="coverageChangeClass(health.coverage.change)">{{ health.coverage.change === null ? "-" : signed(health.coverage.change, ctrans(":change pts", { change: String(Math.abs(health.coverage.change)) }), isFlatCoverage(health.coverage.change)) }}</div>
                    </div>
                    <div class="rounded bg-gray-50 p-3">
                        <div class="text-xs text-gray-500">{{ ctrans("Lines run by tests") }}</div>
                        <div class="mt-1 text-lg font-semibold tabular-nums">{{ health.coverage.hits.toLocaleString() }}</div>
                        <div class="text-xs text-gray-400">{{ ctrans("of :lines", { lines: health.coverage.lines.toLocaleString() }) }}</div>
                    </div>
                </div>
                <div v-if="health.coverage_history.length > 1" class="mt-4 h-44"><Line :aria-label="ctrans('Line coverage over time')" :data="coverageChart" :options="options('%', false)" /></div>
                <p v-if="hasCodecovHistory && firstAikuDay" class="mt-1 text-xs text-gray-500">{{ ctrans("Since :date aiku counts coverage itself. Codecov left out some bracket-only lines, so the line jumps there; changes compare aiku counts only.", { date: formatDay(firstAikuDay) }) }}</p>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-left text-xs text-gray-500">
                                <th class="whitespace-nowrap py-1.5 pr-3 font-medium">{{ ctrans("Module") }}</th>
                                <th class="w-1/3 whitespace-nowrap py-1.5 pr-3 font-medium">{{ ctrans("Coverage") }}</th>
                                <th class="whitespace-nowrap py-1.5 pr-3 text-right font-medium">{{ ctrans("Change (pts)") }}</th>
                                <th class="whitespace-nowrap py-1.5 text-right font-medium">{{ ctrans("Lines of code") }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="module in health.coverage.modules" :key="module.module" class="border-b border-gray-100">
                                <td class="py-1.5 pr-3">{{ module.module }}</td>
                                <td class="py-1.5 pr-3">
                                    <div class="flex items-center gap-2">
                                        <div class="h-1.5 flex-1 rounded bg-gray-100"><div class="h-1.5 rounded" role="presentation" :class="coverageBarClass(module.coverage)" :style="{ width: `${module.coverage ?? 0}%` }" /></div>
                                        <span class="w-14 text-right tabular-nums" :class="coverageClass(module.coverage)">{{ formatPercentage(module.coverage) }}</span>
                                    </div>
                                </td>
                                <td class="py-1.5 pr-3 text-right tabular-nums" :class="coverageChangeClass(module.change)">{{ module.change === null ? "-" : signed(module.change, String(Math.abs(module.change)), isFlatCoverage(module.change)) }}</td>
                                <td class="py-1.5 text-right tabular-nums text-gray-500">{{ module.lines.toLocaleString() }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </section>

        <section class="rounded-lg border border-gray-200 p-4">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <span class="text-sm font-medium">{{ ctrans("Frontend bundle sizes") }}</span>
                <span v-if="health.bundles" class="text-xs text-gray-500">{{ ctrans("Nightly build of :date", { date: formatDate(health.bundles.measured_at) }) }}<template v-if="health.bundles.head_sha"> · {{ health.bundles.head_sha }}</template></span>
            </div>
            <div v-if="!health.bundles" class="mt-3 text-sm text-gray-500">{{ ctrans("No bundle sizes reported yet. The nightly build sends them.") }}</div>
            <template v-else>
                <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <div class="rounded bg-gray-50 p-3">
                        <div class="text-xs text-gray-500">{{ ctrans("JS and CSS") }}</div>
                        <div class="mt-1 text-lg font-semibold tabular-nums">{{ formatBytes(health.bundles.bytes) }}</div>
                    </div>
                    <div class="rounded bg-gray-50 p-3">
                        <div class="text-xs text-gray-500">{{ ctrans("Gzipped") }}</div>
                        <div class="mt-1 text-lg font-semibold tabular-nums">{{ formatBytes(health.bundles.gzip_bytes) }}</div>
                    </div>
                    <div class="rounded bg-gray-50 p-3">
                        <div class="text-xs text-gray-500">{{ ctrans("Gzipped change in :days days", { days: String(health.compare_days) }) }}</div>
                        <div class="mt-1 text-lg font-semibold tabular-nums" :class="sizeChangeClass(health.bundles.change)">{{ health.bundles.change === null ? "-" : signed(health.bundles.change, formatBytes(Math.abs(health.bundles.change)), isFlatSize(health.bundles.change)) }}</div>
                    </div>
                </div>
                <div v-if="health.bundle_history.length > 1" class="mt-4 h-44"><Line :aria-label="ctrans('Bundle size over time')" :data="bundleChart" :options="options(' MB', true)" /></div>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-left text-xs text-gray-500">
                                <th class="whitespace-nowrap py-1.5 pr-3 font-medium">{{ ctrans("Bundle") }}</th>
                                <th class="whitespace-nowrap py-1.5 pr-3 text-right font-medium">{{ ctrans("Files") }}</th>
                                <th class="whitespace-nowrap py-1.5 pr-3 text-right font-medium">JS</th>
                                <th class="whitespace-nowrap py-1.5 pr-3 text-right font-medium">CSS</th>
                                <th class="whitespace-nowrap py-1.5 pr-3 text-right font-medium">{{ ctrans("Gzipped") }}</th>
                                <th class="whitespace-nowrap py-1.5 pr-3 text-right font-medium">{{ ctrans("Change") }}</th>
                                <th class="whitespace-nowrap py-1.5 font-medium">{{ ctrans("Largest file") }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="bundle in health.bundles.bundles" :key="bundle.name" class="border-b border-gray-100">
                                <td class="py-1.5 pr-3">{{ bundle.name }}</td>
                                <td class="py-1.5 pr-3 text-right tabular-nums">{{ bundle.files.toLocaleString() }}</td>
                                <td class="py-1.5 pr-3 text-right tabular-nums">{{ formatBytes(bundle.js_bytes) }}</td>
                                <td class="py-1.5 pr-3 text-right tabular-nums">{{ formatBytes(bundle.css_bytes) }}</td>
                                <td class="py-1.5 pr-3 text-right tabular-nums">{{ formatBytes(bundle.gzip_bytes) }}</td>
                                <td class="py-1.5 pr-3 text-right tabular-nums" :class="sizeChangeClass(bundle.change)">{{ bundle.change === null ? "-" : signed(bundle.change, formatBytes(Math.abs(bundle.change)), isFlatSize(bundle.change)) }}</td>
                                <td class="max-w-xs truncate py-1.5 text-gray-500" :title="bundle.largest[0]?.file">
                                    <template v-if="bundle.largest[0]">{{ bundle.largest[0].file }} · {{ formatBytes(bundle.largest[0].bytes) }}</template>
                                    <template v-else>-</template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </section>
    </div>
</template>
