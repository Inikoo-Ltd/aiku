<script setup lang="ts">
import { computed, ref } from "vue"
import { Bar } from "vue-chartjs"
import { ctrans } from "@/Composables/useTrans"

export type StackFrame = { file: string, source: string, code: Record<string, string> | null, is_vendor: boolean }
export type ExceptionOccurrence = { id: number, created_at: string, handled: boolean, user_id: string | null, execution_preview: string | null, server: string | null, source: string | null }
export type ExceptionDetailData = {
    fingerprint: string
    issue: { id: number, status: string, priority: string | null, exception_class: string | null, exception_message: string | null, first_seen_at: string, last_seen_at: string, occurrences_count: number, users_count: number, assigned_to: string | null } | null
    hourly: { bucket_start: string, handled: number, unhandled: number }[]
    servers: { server: string, occurrences: number }[]
    occurrences: ExceptionOccurrence[]
    occurrence: (ExceptionOccurrence & {
        class: string, message: string, code: string | null, file: string | null, line: number | null, stage: string | null, php_version: string | null, laravel_version: string | null,
        frames: StackFrame[], execution: { kind: "request" | "command", id: number, at: string } | null
    }) | null
}

const props = defineProps<{ exception: ExceptionDetailData }>()
const emit = defineEmits<{ close: [], openOccurrence: [occurrence: ExceptionOccurrence], openTrace: [kind: "request" | "command", row: { id: number, created_at: string }] }>()

const showVendor = ref(false)
const openedFrames = ref<Set<number>>(new Set([0]))
const toggleFrame = (index: number) => {
    const opened = new Set(openedFrames.value)
    opened.has(index) ? opened.delete(index) : opened.add(index)
    openedFrames.value = opened
}

const frames = computed(() => (props.exception.occurrence?.frames ?? []).map((frame, index) => ({ ...frame, index })))
const visibleFrames = computed(() => showVendor.value ? frames.value : frames.value.filter(frame => !frame.is_vendor || frame.index === 0))
const hiddenVendorFrames = computed(() => frames.value.length - visibleFrames.value.length)
const lineOf = (frame: StackFrame) => Number(frame.file.split(":").pop())
const fileOf = (frame: StackFrame) => frame.file.replace(/:\d+$/, "")

const chart = computed(() => ({
    labels: props.exception.hourly.map(point => new Date(point.bucket_start.replace(" ", "T") + "Z").toLocaleString(undefined, { weekday: "short", hour: "2-digit", minute: "2-digit" })),
    datasets: [
        { label: ctrans("Handled"), data: props.exception.hourly.map(point => Number(point.handled)), backgroundColor: "#10b981", stack: "total" },
        { label: ctrans("Unhandled"), data: props.exception.hourly.map(point => Number(point.unhandled)), backgroundColor: "#e11d48", stack: "total" },
    ],
}))
const chartOptions = { responsive: true, maintainAspectRatio: false, animation: false as const, plugins: { legend: { display: false } }, scales: { x: { stacked: true, ticks: { maxTicksLimit: 6 }, grid: { display: false } }, y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } } } }

const title = computed(() => props.exception.occurrence?.class ?? props.exception.issue?.exception_class ?? ctrans("Exception"))
const message = computed(() => props.exception.occurrence?.message ?? props.exception.issue?.exception_message)
</script>

<template>
    <div class="rounded border bg-white p-4">
        <div class="mb-4 flex items-start justify-between gap-4">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="break-all font-mono text-sm font-semibold">{{ title }}</span>
                    <span v-if="exception.issue" class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-600">{{ exception.issue.status }}<template v-if="exception.issue.priority"> · {{ exception.issue.priority }}</template></span>
                    <span v-if="exception.occurrence" class="rounded px-2 py-0.5 text-xs" :class="exception.occurrence.handled ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'">
                        {{ exception.occurrence.handled ? ctrans("Handled") : ctrans("Unhandled") }}
                    </span>
                </div>
                <div class="mt-1 whitespace-pre-wrap break-words text-sm text-gray-800">{{ message }}</div>
                <div v-if="exception.occurrence?.file" class="mt-1 font-mono text-xs text-gray-500">{{ exception.occurrence.file }}:{{ exception.occurrence.line }}</div>
                <div class="mt-1 text-xs text-gray-500">
                    <template v-if="exception.issue">
                        {{ exception.issue.occurrences_count }} {{ ctrans("occurrences") }} · {{ exception.issue.users_count }} {{ ctrans("users") }} ·
                        {{ ctrans("first seen") }} {{ exception.issue.first_seen_at }} · {{ ctrans("last seen") }} {{ exception.issue.last_seen_at }} UTC
                    </template>
                </div>
            </div>
            <button class="text-sm text-gray-500 hover:text-gray-800" @click="emit('close')">{{ ctrans("Close") }}</button>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <div class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ ctrans("Last 7 days") }}</div>
                <div class="h-32"><Bar :data="chart" :options="chartOptions" /></div>
            </div>
            <div>
                <div class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ ctrans("Servers") }}</div>
                <div v-for="server in exception.servers" :key="server.server" class="flex justify-between border-b py-1 text-sm"><span>{{ server.server }}</span><span class="tabular-nums">{{ server.occurrences }}</span></div>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_20rem]">
            <div class="min-w-0">
                <div v-if="exception.occurrence" class="mb-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500">
                    <span class="font-medium uppercase tracking-wide">{{ ctrans("Stack trace") }}</span>
                    <span>{{ exception.occurrence.created_at }} UTC · {{ exception.occurrence.server }} · {{ exception.occurrence.source }}<template v-if="exception.occurrence.stage"> ({{ exception.occurrence.stage }})</template></span>
                    <span v-if="exception.occurrence.execution_preview" class="font-mono">{{ exception.occurrence.execution_preview }}</span>
                    <span>PHP {{ exception.occurrence.php_version }} · Laravel {{ exception.occurrence.laravel_version }}</span>
                    <button v-if="exception.occurrence.execution" class="text-indigo-600 hover:underline"
                        @click="emit('openTrace', exception.occurrence.execution.kind, { id: exception.occurrence.execution.id, created_at: exception.occurrence.execution.at })">
                        {{ exception.occurrence.execution.kind === "request" ? ctrans("Open request waterfall") : ctrans("Open command waterfall") }}
                    </button>
                    <button v-if="hiddenVendorFrames || showVendor" class="ml-auto text-indigo-600 hover:underline" @click="showVendor = !showVendor">
                        {{ showVendor ? ctrans("Hide vendor frames") : `${ctrans("Show vendor frames")} (${hiddenVendorFrames})` }}
                    </button>
                </div>
                <div v-if="!exception.occurrence" class="text-sm text-gray-500">{{ ctrans("No stored occurrence with a stack trace in the last 7 days") }}</div>
                <div v-else class="divide-y rounded border">
                    <div v-for="frame in visibleFrames" :key="frame.index" :class="frame.is_vendor ? 'bg-gray-50' : ''">
                        <button class="flex w-full items-baseline gap-2 px-3 py-1.5 text-left font-mono text-xs hover:bg-indigo-50" @click="toggleFrame(frame.index)">
                            <span class="w-6 shrink-0 text-right text-gray-400">{{ frame.index }}</span>
                            <span class="min-w-0 flex-1">
                                <span v-if="frame.source" class="block truncate text-gray-800">{{ frame.source }}</span>
                                <span class="block truncate" :class="frame.is_vendor ? 'text-gray-400' : 'text-indigo-700'">{{ frame.file }}</span>
                            </span>
                        </button>
                        <div v-if="frame.code && openedFrames.has(frame.index)" class="overflow-x-auto bg-gray-900 py-1 font-mono text-xs leading-5">
                            <div v-for="(code, line) in frame.code" :key="line" class="flex whitespace-pre" :class="Number(line) === lineOf(frame) ? 'bg-rose-900/60 text-white' : 'text-gray-300'">
                                <span class="w-12 shrink-0 select-none pr-3 text-right text-gray-500">{{ line }}</span><span>{{ code }}</span>
                            </div>
                            <div class="px-3 pt-1 text-[10px] text-gray-500">{{ fileOf(frame) }}</div>
                        </div>
                    </div>
                </div>
            </div>
            <div>
                <div class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-500">{{ ctrans("Recent occurrences") }}</div>
                <button v-for="occurrence in exception.occurrences" :key="occurrence.id"
                    class="block w-full border-b px-2 py-1.5 text-left text-xs hover:bg-indigo-50" :class="occurrence.id === exception.occurrence?.id ? 'bg-indigo-50' : ''"
                    @click="emit('openOccurrence', occurrence)">
                    <div class="flex justify-between gap-2"><span>{{ occurrence.created_at }}</span><span class="text-gray-500">{{ occurrence.server }} · {{ occurrence.source }}</span></div>
                    <div v-if="occurrence.execution_preview" class="truncate font-mono text-gray-500">{{ occurrence.execution_preview }}</div>
                    <div v-if="occurrence.user_id" class="text-gray-400">{{ ctrans("user") }} {{ occurrence.user_id }}</div>
                </button>
            </div>
        </div>
    </div>
</template>
