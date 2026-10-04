<script lang="ts">
export const formatMicroseconds = (microseconds: number | null | undefined) => {
    const value = Number(microseconds) || 0
    if (value < 1000) {
        return `${value.toFixed(0)}µs`
    }
    if (value < 1_000_000) {
        return `${(value / 1000).toFixed(value < 10_000 ? 2 : 0)}ms`
    }
    return `${(value / 1_000_000).toFixed(2)}s`
}
</script>

<script setup lang="ts">
import { computed, ref } from "vue"
import { ctrans } from "@/Composables/useTrans"
import SqlCode from "@/Components/DevOps/SqlCode.vue"

export type TraceSpan = { type: "query" | "cache" | "http" | "exception", offset: number, duration: number, label: string, file: string | null, line: number | null }
export type TraceRequest = {
    id: number, created_at: string, url: string, status_code: number, duration: number, user_id: string | null, method: string | null, route_name: string | null, route_path: string | null,
    bootstrap: number, before_middleware: number, action: number, render: number, after_middleware: number, sending: number, terminating: number, peak_memory_usage: number, exception_preview: string | null
}

const props = defineProps<{ trace: { request: TraceRequest, spans: TraceSpan[] } }>()
defineEmits<{ close: [] }>()

const colours: Record<TraceSpan["type"], string> = { query: "bg-sky-500", cache: "bg-emerald-500", http: "bg-amber-500", exception: "bg-rose-600" }
const typeLabels = computed<Record<TraceSpan["type"], string>>(() => ({ query: ctrans("Query"), cache: ctrans("Cache"), http: ctrans("HTTP"), exception: ctrans("Exception") }))

const stages = computed(() => {
    const request = props.trace.request
    let start = 0
    return ([
        ["bootstrap", ctrans("Bootstrap"), "bg-gray-400"],
        ["before_middleware", ctrans("Middleware"), "bg-indigo-300"],
        ["action", ctrans("Controller"), "bg-indigo-500"],
        ["render", ctrans("Render"), "bg-violet-500"],
        ["after_middleware", ctrans("After middleware"), "bg-indigo-300"],
        ["sending", ctrans("Sending"), "bg-gray-400"],
        ["terminating", ctrans("Terminating"), "bg-gray-300"],
    ] as const).map(([key, label, colour]) => {
        const stage = { label, colour, offset: start, duration: Number(request[key]) || 0 }
        start += stage.duration
        return stage
    }).filter(stage => stage.duration > 0)
})

const total = computed(() => Math.max(props.trace.request.duration, ...props.trace.spans.map(span => span.offset + span.duration), 1))
const position = (offset: number, duration: number) => ({
    left: `${Math.max(0, offset) / total.value * 100}%`,
    width: `max(2px, ${duration / total.value * 100}%)`,
})

const counts = computed(() => props.trace.spans.reduce<Record<string, { count: number, time: number }>>((totals, span) => {
    totals[span.type] ??= { count: 0, time: 0 }
    totals[span.type].count++
    totals[span.type].time += span.duration
    return totals
}, {}))

const opened = ref<number | null>(null)
</script>

<template>
    <div class="rounded border bg-white p-4">
        <div class="mb-3 flex items-start justify-between gap-4">
            <div class="min-w-0">
                <div class="font-mono text-sm"><span class="font-semibold">{{ trace.request.method }}</span> {{ trace.request.url }}</div>
                <div class="mt-1 text-xs text-gray-500">
                    {{ trace.request.route_name }} · {{ trace.request.status_code }} · {{ formatMicroseconds(trace.request.duration) }} ·
                    {{ (trace.request.peak_memory_usage / 1048576).toFixed(0) }} MB · {{ trace.request.created_at }} UTC
                    <template v-if="trace.request.user_id"> · {{ ctrans("user") }} {{ trace.request.user_id }}</template>
                </div>
                <div class="mt-1 flex flex-wrap gap-3 text-xs">
                    <span v-for="(total, type) in counts" :key="type" class="flex items-center gap-1">
                        <span class="inline-block h-2 w-2 rounded-sm" :class="colours[type as TraceSpan['type']]" />
                        {{ typeLabels[type as TraceSpan['type']] }} {{ total.count }} · {{ formatMicroseconds(total.time) }}
                    </span>
                </div>
                <div v-if="trace.request.exception_preview" class="mt-1 text-xs text-rose-700">{{ trace.request.exception_preview }}</div>
            </div>
            <button class="text-sm text-gray-500 hover:text-gray-800" @click="$emit('close')">{{ ctrans("Close") }}</button>
        </div>

        <div class="grid grid-cols-[minmax(0,2fr)_minmax(0,3fr)_5rem] items-center gap-x-3 gap-y-1 text-xs">
            <template v-for="stage in stages" :key="stage.label">
                <div class="truncate font-medium text-gray-600">{{ stage.label }}</div>
                <div class="relative h-3 rounded bg-gray-50"><div class="absolute top-0 h-3 rounded" :class="stage.colour" :style="position(stage.offset, stage.duration)" /></div>
                <div class="text-right tabular-nums text-gray-500">{{ formatMicroseconds(stage.duration) }}</div>
            </template>
            <div class="col-span-3 my-1 border-t" />
            <template v-for="(span, index) in trace.spans" :key="index">
                <button class="truncate text-left font-mono text-gray-700 hover:text-black" :title="span.label" @click="opened = opened === index ? null : index">
                    <span class="mr-1 inline-block h-2 w-2 rounded-sm" :class="colours[span.type]" />{{ span.label }}
                </button>
                <div class="relative h-3 rounded bg-gray-50"><div class="absolute top-0 h-3 rounded" :class="colours[span.type]" :style="position(span.offset, span.duration)" /></div>
                <div class="text-right tabular-nums text-gray-500">{{ formatMicroseconds(span.duration) }}</div>
                <div v-if="opened === index" class="col-span-3 mb-2 rounded bg-gray-50 p-2">
                    <SqlCode v-if="span.type === 'query'" :sql="span.label" />
                    <div v-else class="break-all font-mono">{{ span.label }}</div>
                    <div v-if="span.file" class="mt-1 text-gray-500">{{ span.file }}:{{ span.line }}</div>
                    <div class="mt-1 text-gray-500">{{ ctrans("Starts at") }} {{ formatMicroseconds(span.offset) }}</div>
                </div>
            </template>
            <div v-if="!trace.spans.length" class="col-span-3 py-2 text-gray-500">{{ ctrans("No queries, cache events or outgoing requests were recorded for this request") }}</div>
        </div>
    </div>
</template>
