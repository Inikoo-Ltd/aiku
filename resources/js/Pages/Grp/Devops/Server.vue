<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3"
import { computed } from "vue"
import { Line } from "vue-chartjs"
import { Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement, Tooltip, Legend, Filler } from "chart.js"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { PageHeadingTypes } from "@/types/PageHeading"
import ServerUsageCard, { ServerSummary } from "@/Components/DevOps/ServerUsageCard.vue"

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Tooltip, Legend, Filler)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    server: ServerSummary
    range: string
    ranges: string[]
    series: { t: string, cpu: number, cpu_max: number, memory: number, memory_max: number, swap: number | null, disk: number, load: number | null, iowait: number | null, iowait_max: number | null, inode: number | null,
        net_rx: number | null, net_tx: number | null, disk_read: number | null, disk_write: number | null, processes: number | null, tcp_connections: number | null }[]
}>()

const isAggregated = computed(() => props.range !== "24h")

const labels = computed(() => props.series.map(point => point.t))

const line = (label: string, values: (number | null)[], colour: string, dashed = false) => ({
    label,
    data: values.map(value => value === null ? null : Number(value)),
    borderColor: colour,
    backgroundColor: colour,
    borderWidth: 1.5,
    borderDash: dashed ? [4, 3] : [],
    pointRadius: 0,
    tension: 0.2,
    spanGaps: true,
})

const usageChart = computed(() => ({
    labels: labels.value,
    datasets: [
        line(ctrans("CPU"), props.series.map(p => p.cpu), "#6366f1"),
        ...(isAggregated.value ? [line(ctrans("CPU peak"), props.series.map(p => p.cpu_max), "#6366f1", true)] : []),
        line(ctrans("Memory"), props.series.map(p => p.memory), "#0d9488"),
        ...(isAggregated.value ? [line(ctrans("Memory peak"), props.series.map(p => p.memory_max), "#0d9488", true)] : []),
        line(ctrans("I/O wait"), props.series.map(p => p.iowait), "#a855f7"),
        ...(isAggregated.value ? [line(ctrans("I/O wait peak"), props.series.map(p => p.iowait_max), "#a855f7", true)] : []),
        line(ctrans("Swap"), props.series.map(p => p.swap), "#f59e0b"),
        line(ctrans("Disk"), props.series.map(p => p.disk), "#ef4444"),
        line(ctrans("Inodes"), props.series.map(p => p.inode), "#ef4444", true),
    ],
}))

const loadChart = computed(() => ({
    labels: labels.value,
    datasets: [line(isAggregated.value ? ctrans("Load (peak)") : ctrans("Load"), props.series.map(p => p.load), "#64748b")],
}))

const throughputChart = computed(() => ({
    labels: labels.value,
    datasets: [
        line(ctrans("Network in"), props.series.map(p => p.net_rx), "#0ea5e9"),
        line(ctrans("Network out"), props.series.map(p => p.net_tx), "#0ea5e9", true),
        line(ctrans("Disk read"), props.series.map(p => p.disk_read), "#f97316"),
        line(ctrans("Disk write"), props.series.map(p => p.disk_write), "#f97316", true),
    ],
}))

const countsChart = computed(() => ({
    labels: labels.value,
    datasets: [
        line(isAggregated.value ? ctrans("Processes (peak)") : ctrans("Processes"), props.series.map(p => p.processes), "#64748b"),
        line(isAggregated.value ? ctrans("TCP connections (peak)") : ctrans("TCP connections"), props.series.map(p => p.tcp_connections), "#10b981"),
    ],
}))

const options = (max?: number) => ({
    responsive: true,
    maintainAspectRatio: false,
    animation: false as const,
    interaction: { mode: "index" as const, intersect: false },
    scales: { y: { min: 0, ...(max ? { max } : {}) }, x: { ticks: { maxTicksLimit: 12 } } },
})
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="space-y-4 p-4">
        <div class="max-w-sm">
            <ServerUsageCard :server="server" />
        </div>

        <div class="flex gap-2 text-xs">
            <Link v-for="option in ranges" :key="option" :href="route('grp.devops.servers.show', { server: server.slug, range: option })" preserve-scroll
                class="rounded border px-2 py-1" :class="option === range ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-200 text-gray-600 hover:bg-gray-50'">
                {{ option }}
            </Link>
            <span class="self-center text-gray-400">
                {{ isAggregated ? ctrans("Hourly/daily averages with peaks, kept forever") : ctrans("Every minute, kept 90 days") }}
            </span>
        </div>

        <div v-if="!series.length" class="text-sm text-gray-500">{{ ctrans("No data for this range yet") }}</div>
        <template v-else>
            <div class="rounded-lg border border-gray-200 p-4">
                <h3 class="mb-2 text-sm font-semibold">{{ ctrans("Usage %") }}</h3>
                <div class="h-72"><Line :data="usageChart" :options="options(100)" /></div>
            </div>
            <div class="rounded-lg border border-gray-200 p-4">
                <h3 class="mb-2 text-sm font-semibold">{{ ctrans("Load average (1 min)") }}</h3>
                <div class="h-48"><Line :data="loadChart" :options="options()" /></div>
            </div>
            <div class="rounded-lg border border-gray-200 p-4">
                <h3 class="mb-2 text-sm font-semibold">{{ isAggregated ? ctrans("Network & disk MB/s (averages)") : ctrans("Network & disk MB/s") }}</h3>
                <div class="h-48"><Line :data="throughputChart" :options="options()" /></div>
            </div>
            <div class="rounded-lg border border-gray-200 p-4">
                <h3 class="mb-2 text-sm font-semibold">{{ ctrans("Processes & connections") }}</h3>
                <div class="h-48"><Line :data="countsChart" :options="options()" /></div>
            </div>
        </template>
    </div>
</template>
