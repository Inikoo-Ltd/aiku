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

const meters = computed(() => [
    { label: ctrans("CPU"), value: latestLive.value?.cpu_percent ?? props.server.cpu_percent, peak: props.server.cpu_24h_max },
    { label: ctrans("I/O wait"), value: latestLive.value?.iowait_percent ?? props.server.iowait_percent, peak: null },
    { label: ctrans("Memory"), value: latestLive.value?.memory_percent ?? props.server.memory_percent, peak: props.server.memory_24h_max },
    { label: ctrans("Swap"), value: props.server.swap_percent, peak: null },
    { label: ctrans("Disk"), value: props.server.disk_percent, peak: null },
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
                        <span v-if="meter.peak != null" class="text-gray-400">· {{ ctrans("24h peak") }} {{ Number(meter.peak).toFixed(0) }}%</span>
                    </span>
                </div>
                <div class="mt-0.5 h-1.5 rounded bg-gray-100">
                    <div class="h-1.5 rounded" :class="barColour(meter.value)" :style="{ width: `${Math.min(Number(meter.value ?? 0), 100)}%` }" />
                </div>
            </div>
        </div>
        <div class="mt-3 text-xs text-gray-500">
            <span v-if="server.load_1 != null">{{ ctrans("Load") }} {{ server.load_1 }} / {{ server.cpu_cores }} {{ ctrans("cores") }}</span>
            <span v-if="server.memory_total_mb"> · {{ (server.memory_total_mb / 1024).toFixed(0) }} GB RAM</span>
        </div>
        <div v-if="server.net_rx_mbps != null" class="mt-1 grid grid-cols-2 gap-x-2 text-xs text-gray-500 tabular-nums">
            <span>{{ ctrans("Net") }} ↓{{ latestLive?.net_rx_mbps ?? server.net_rx_mbps }} ↑{{ latestLive?.net_tx_mbps ?? server.net_tx_mbps }} MB/s</span>
            <span>{{ ctrans("Disk") }} R{{ server.disk_read_mbps }} W{{ server.disk_write_mbps }} MB/s</span>
            <span>{{ server.processes }} {{ ctrans("processes") }}</span>
            <span>{{ server.tcp_connections }} {{ ctrans("connections") }}</span>
        </div>
        <div v-if="disks.length > 1" class="mt-1 space-y-0.5 text-xs text-gray-500">
            <div v-for="disk in disks" :key="disk.mount" class="flex justify-between">
                <span class="truncate">{{ disk.mount }}</span>
                <span class="tabular-nums">{{ disk.percent }}% of {{ disk.size_gb }} GB<template v-if="disk.inode_percent != null"> · {{ ctrans("inodes") }} {{ disk.inode_percent }}%</template></span>
            </div>
        </div>
    </div>
</template>
