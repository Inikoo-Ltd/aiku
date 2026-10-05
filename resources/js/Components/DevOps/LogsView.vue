<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { Bar } from "vue-chartjs"
import { ctrans } from "@/Composables/useTrans"

export type LogEntry = {
    id: number, created_at: string, execution_id: string | null, user_id: string | null, execution_preview: string | null, message: string,
    context: string | null, level: string | null, channel: string | null, server: string | null, source: string | null
}
export type Logs = { range: string, level: string | null, search: string | null, series: { bucket_start: string, level: string, entries: number }[], entries: LogEntry[] }

const props = defineProps<{ logs: Logs }>()
const emit = defineEmits<{ filter: [filters: { level: string | null, search: string | null }], openExecution: [entry: LogEntry] }>()

const LEVELS: [string, string, string][] = [
    ["emergency", "#7f1d1d", "bg-red-900 text-white"], ["alert", "#991b1b", "bg-red-800 text-white"], ["critical", "#b91c1c", "bg-red-700 text-white"],
    ["error", "#e11d48", "bg-rose-100 text-rose-700"], ["warning", "#f59e0b", "bg-amber-100 text-amber-800"], ["notice", "#0ea5e9", "bg-sky-100 text-sky-700"],
    ["info", "#94a3b8", "bg-slate-100 text-slate-600"], ["debug", "#cbd5e1", "bg-gray-100 text-gray-500"],
]
const levelClass = (level: string | null) => LEVELS.find(([name]) => name === level)?.[2] ?? "bg-gray-100 text-gray-600"

const search = ref(props.logs.search ?? "")
watch(() => props.logs.search, value => search.value = value ?? "")

const chart = computed(() => {
    const buckets = [...new Set(props.logs.series.map(point => point.bucket_start))].sort()
    const present = LEVELS.filter(([name]) => props.logs.series.some(point => point.level === name))
    return {
        labels: buckets.map(bucket => new Date(bucket.replace(" ", "T") + "Z").toLocaleString(undefined, props.logs.range === "7d" ? { weekday: "short", hour: "2-digit", minute: "2-digit" } : { hour: "2-digit", minute: "2-digit" })),
        datasets: present.map(([name, colour]) => ({
            label: name,
            data: buckets.map(bucket => Number(props.logs.series.find(point => point.bucket_start === bucket && point.level === name)?.entries ?? 0)),
            backgroundColor: colour,
            stack: "total",
        })),
    }
})
const chartOptions = { responsive: true, maintainAspectRatio: false, animation: false as const, plugins: { legend: { display: true, position: "bottom" as const, labels: { boxWidth: 8 } } }, scales: { x: { stacked: true, ticks: { maxTicksLimit: 8 }, grid: { display: false } }, y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } } } }
const total = computed(() => props.logs.series.reduce((sum, point) => sum + Number(point.entries), 0))

const opened = ref<number | null>(null)
</script>

<template>
    <div>
        <div class="flex flex-wrap items-center gap-2 border-b px-3 py-2">
            <button class="rounded px-2 py-1 text-xs" :class="!logs.level ? 'bg-gray-800 text-white' : 'hover:bg-gray-100'" @click="emit('filter', { level: null, search: logs.search })">{{ ctrans("All levels") }}</button>
            <button v-for="[name] in LEVELS.slice(2, 7)" :key="name" class="rounded px-2 py-1 text-xs" :class="logs.level === name ? 'bg-gray-800 text-white' : 'hover:bg-gray-100'"
                @click="emit('filter', { level: name, search: logs.search })">{{ name }}</button>
            <form class="ml-auto flex gap-1" @submit.prevent="emit('filter', { level: logs.level, search: search || null })">
                <input v-model="search" type="search" class="w-64 rounded border-gray-300 px-2 py-1 text-sm" :placeholder="ctrans('Search messages')" />
                <button class="rounded border px-2 py-1 text-xs hover:bg-gray-50">{{ ctrans("Search") }}</button>
            </form>
        </div>

        <div v-if="total" class="h-40 px-3 pt-2"><Bar :data="chart" :options="chartOptions" /></div>

        <div v-if="!logs.entries.length" class="px-3 py-6 text-sm text-gray-500">
            {{ ctrans("No log entries in this range. Logs reach NightOwl only from servers whose LOG_STACK includes nightwatch.") }}
        </div>
        <div v-else class="divide-y text-sm">
            <div v-for="entry in logs.entries" :key="entry.id">
                <button class="flex w-full items-baseline gap-2 px-3 py-1.5 text-left hover:bg-indigo-50" @click="opened = opened === entry.id ? null : entry.id">
                    <span class="w-36 shrink-0 whitespace-nowrap text-xs text-gray-500">{{ entry.created_at }}</span>
                    <span class="w-16 shrink-0 rounded px-1.5 text-center text-[11px] uppercase" :class="levelClass(entry.level)">{{ entry.level }}</span>
                    <span class="min-w-0 flex-1 truncate font-mono text-xs">{{ entry.message }}</span>
                    <span class="shrink-0 text-xs text-gray-400">{{ entry.server }}</span>
                </button>
                <div v-if="opened === entry.id" class="space-y-1 bg-gray-50 px-3 py-2 text-xs">
                    <div class="whitespace-pre-wrap break-words font-mono">{{ entry.message }}</div>
                    <div class="text-gray-500">
                        {{ entry.channel }} · {{ entry.source }}<template v-if="entry.execution_preview"> · <span class="font-mono">{{ entry.execution_preview }}</span></template>
                        <template v-if="entry.user_id"> · {{ ctrans("user") }} {{ entry.user_id }}</template>
                    </div>
                    <button v-if="entry.execution_id && (entry.source === 'request' || entry.source === 'command')" class="text-indigo-600 hover:underline" @click="emit('openExecution', entry)">
                        {{ entry.source === "request" ? ctrans("Open request waterfall") : ctrans("Open command waterfall") }}
                    </button>
                    <pre v-if="entry.context" class="max-h-80 overflow-auto rounded bg-gray-900 p-2 text-[11px] leading-4 text-gray-100">{{ entry.context }}</pre>
                </div>
            </div>
        </div>
    </div>
</template>
