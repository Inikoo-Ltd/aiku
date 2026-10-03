<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from "vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { LiveServerReading } from "@/Composables/useLiveServerMetrics"

export interface ServerSummary {
    slug: string
    name: string
    recorded_at: string | null
    cpu_percent: number | null
    memory_percent: number | null
    swap_percent: number | null
    disk_percent: number | null
    load_1: number | null
    iowait_percent: number | null
    inode_percent: number | null
    net_rx_mbps: number | null
    net_tx_mbps: number | null
    disk_read_mbps: number | null
    disk_write_mbps: number | null
    processes: number | null
    tcp_connections: number | null
    cpu_cores: number | null
    memory_total_mb: number | null
    swap_total_mb: number | null
    disks: string | null
    cpu_24h_max: number | null
    memory_24h_max: number | null
}

const props = defineProps<{ server: ServerSummary, live?: LiveServerReading[] }>()

const now = ref(Date.now())
let clock: ReturnType<typeof setInterval> | undefined
onMounted(() => (clock = setInterval(() => (now.value = Date.now()), 5000)))
onBeforeUnmount(() => clearInterval(clock))

const latestLive = computed(() => {
    const reading = props.live?.at(-1)
    return reading && now.value - reading.t < 30 * 1000 ? reading : null
})

const liveSparkline = computed(() => {
    const points = props.live ?? []
    if (points.length < 2) return ""
    return points.map((reading, index) => `${(index / (points.length - 1)) * 100},${30 - (Number(reading.cpu_percent) / 100) * 28}`).join(" ")
})

const isStale = computed(() => !latestLive.value && (!props.server.recorded_at || now.value - new Date(props.server.recorded_at).getTime() > 5 * 60 * 1000))

const disks = computed<{ mount: string, percent: number, size_gb: number, inode_percent: number | null }[]>(() => props.server.disks ? JSON.parse(props.server.disks) : [])

const mainDiskGb = computed(() => disks.value.reduce<{ percent: number, size_gb: number } | null>((main, disk) => !main || disk.percent > main.percent ? disk : main, null)?.size_gb ?? null)

const formatSize = (gb: number) => gb >= 1000 ? `${(gb / 1024).toFixed(1)} TB` : `${gb.toFixed(gb < 10 ? 1 : 0)} GB`

const amount = (percent: number | null | undefined, totalGb: number | null) => {
    if (percent == null || !totalGb) return null
    const unitGb = totalGb >= 1000 ? 1024 : 1
    const unit = totalGb >= 1000 ? "TB" : "GB"
    const used = (Number(percent) / 100) * totalGb / unitGb
    const total = totalGb / unitGb
    const digits = total < 10 ? 1 : 0
    return `${used.toFixed(digits)} / ${total.toFixed(digits)} ${unit}`
}

const details = computed(() => {
    const server = props.server
    const rows: { label: string, value: string }[] = []
    if (server.load_1 != null) rows.push({ label: ctrans("Load"), value: `${server.load_1} / ${server.cpu_cores} ${ctrans("cores")}` })
    if (server.memory_total_mb) rows.push({ label: ctrans("RAM"), value: formatSize(server.memory_total_mb / 1024) })
    const rx = latestLive.value?.net_rx_mbps ?? server.net_rx_mbps
    if (rx != null) rows.push({ label: ctrans("Network in / out"), value: `${rx} / ${latestLive.value?.net_tx_mbps ?? server.net_tx_mbps} MB/s` })
    if (server.disk_read_mbps != null) rows.push({ label: ctrans("Disk read / write"), value: `${server.disk_read_mbps} / ${server.disk_write_mbps} MB/s` })
    if (server.processes != null) rows.push({ label: ctrans("Processes"), value: `${server.processes}` })
    if (server.tcp_connections != null) rows.push({ label: ctrans("Connections"), value: `${server.tcp_connections}` })
    return rows
})

const meters = computed(() => [
    { label: ctrans("CPU"), value: latestLive.value?.cpu_percent ?? props.server.cpu_percent, peak: props.server.cpu_24h_max },
    { label: ctrans("I/O wait"), value: latestLive.value?.iowait_percent ?? props.server.iowait_percent, peak: null },
    { label: ctrans("Memory"), value: latestLive.value?.memory_percent ?? props.server.memory_percent, peak: props.server.memory_24h_max,
        amount: amount(latestLive.value?.memory_percent ?? props.server.memory_percent, props.server.memory_total_mb ? props.server.memory_total_mb / 1024 : null) },
    { label: ctrans("Swap"), value: props.server.swap_percent, peak: null, amount: amount(props.server.swap_percent, props.server.swap_total_mb ? props.server.swap_total_mb / 1024 : null) },
    { label: ctrans("Disk"), value: props.server.disk_percent, peak: null, amount: amount(props.server.disk_percent, mainDiskGb.value) },
    { label: ctrans("Inodes"), value: props.server.inode_percent, peak: null },
])

const barColour = (value: number | null | undefined) => value == null ? "bg-gray-200" : value >= 90 ? "bg-red-500" : value >= 75 ? "bg-amber-500" : "bg-emerald-500"
</script>

<template>
    <div class="rounded-lg border border-gray-200 p-4" :class="isStale ? 'opacity-60' : ''">
        <div class="flex items-baseline justify-between">
            <span class="text-sm font-medium">{{ server.name }}</span>
            <span v-if="latestLive" class="flex items-center gap-1 text-xs text-emerald-600">
                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500" />{{ ctrans("Live") }}
            </span>
            <span v-else class="text-xs" :class="isStale ? 'text-red-600' : 'text-gray-500'">
                {{ server.recorded_at ? useFormatTime(server.recorded_at, { formatTime: "hm" }) : ctrans("No data yet") }}
            </span>
        </div>
        <svg v-if="liveSparkline" viewBox="0 0 100 30" class="mt-2 h-8 w-full" preserveAspectRatio="none">
            <polyline :points="liveSparkline" fill="none" stroke="currentColor" stroke-width="1.5" vector-effect="non-scaling-stroke" class="text-indigo-500" />
        </svg>
        <div class="mt-3 space-y-2">
            <div v-for="meter in meters" :key="meter.label">
                <div class="flex justify-between text-xs">
                    <span class="text-gray-600">{{ meter.label }}</span>
                    <span class="tabular-nums">
                        {{ meter.value == null ? "-" : `${Number(meter.value).toFixed(0)}%` }}
                        <span v-if="meter.amount" class="text-gray-400">· {{ meter.amount }}</span>
                        <span v-if="meter.peak != null" class="text-gray-400">· {{ ctrans("24h peak") }} {{ Number(meter.peak).toFixed(0) }}%</span>
                    </span>
                </div>
                <div class="mt-0.5 h-1.5 rounded bg-gray-100">
                    <div class="h-1.5 rounded" :class="barColour(meter.value)" :style="{ width: `${Math.min(Number(meter.value ?? 0), 100)}%` }" />
                </div>
            </div>
        </div>
        <table class="mt-3 w-full text-xs tabular-nums">
            <tbody class="divide-y divide-gray-100">
                <tr v-for="row in details" :key="row.label">
                    <td class="py-1 text-gray-500">{{ row.label }}</td>
                    <td class="py-1 text-right">{{ row.value }}</td>
                </tr>
            </tbody>
        </table>
        <table v-if="disks.length" class="mt-3 w-full text-xs tabular-nums">
            <thead class="text-gray-400">
                <tr>
                    <th class="pb-1 text-left font-normal">{{ ctrans("Mount") }}</th>
                    <th class="pb-1 text-right font-normal">{{ ctrans("Used") }}</th>
                    <th class="pb-1 text-right font-normal">{{ ctrans("Size") }}</th>
                    <th class="pb-1 text-right font-normal">{{ ctrans("Inodes") }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <tr v-for="disk in disks" :key="disk.mount">
                    <td class="max-w-0 truncate py-1 text-gray-500">{{ disk.mount }}</td>
                    <td class="py-1 text-right">{{ disk.percent }}%</td>
                    <td class="py-1 text-right">{{ formatSize(disk.size_gb) }}</td>
                    <td class="py-1 text-right">{{ disk.inode_percent != null ? `${disk.inode_percent}%` : "-" }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
