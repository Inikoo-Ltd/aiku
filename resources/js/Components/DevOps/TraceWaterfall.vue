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
export type Trace = {
    summary: { kind: "request" | "job" | "command", title: string, subtitle: string | null, status: string | null, failed: boolean, duration: number, created_at: string, user_id: string | null, peak_memory_usage: number, exception_preview: string | null }
    stages: { label: string, duration: number }[]
    spans: TraceSpan[]
    truncated: boolean
}

const props = defineProps<{ trace: Trace }>()
defineEmits<{ close: [] }>()

const colours: Record<TraceSpan["type"], string> = { query: "bg-sky-500", cache: "bg-emerald-500", http: "bg-amber-500", exception: "bg-rose-600" }
const typeLabels = computed<Record<TraceSpan["type"], string>>(() => ({ query: ctrans("Query"), cache: ctrans("Cache"), http: ctrans("HTTP"), exception: ctrans("Exception") }))
const stageColours = ["bg-gray-400", "bg-indigo-300", "bg-indigo-500", "bg-violet-500", "bg-indigo-300", "bg-gray-400", "bg-gray-300"]

const stages = computed(() => {
    let start = 0
    return props.trace.stages.map((stage, index) => {
        const positioned = { ...stage, offset: start, colour: stageColours[index % stageColours.length] }
        start += stage.duration
        return positioned
    })
})

const total = computed(() => Math.max(props.trace.summary.duration, ...props.trace.spans.map(span => span.offset + span.duration), 1))
const position = (offset: number, duration: number) => ({
    left: `${Math.max(0, offset) / total.value * 100}%`,
    width: `max(2px, ${duration / total.value * 100}%)`,
})

const counts = computed(() => props.trace.spans.reduce<Partial<Record<TraceSpan["type"], { count: number, time: number }>>>((totals, span) => {
    const total = totals[span.type] ??= { count: 0, time: 0 }
    total.count++
    total.time += span.duration
    return totals
}, {}))

const opened = ref<number | null>(null)
</script>

<template>
    <div class="rounded border bg-white p-4">
        <div class="mb-3 flex items-start justify-between gap-4">
            <div class="min-w-0">
                <div class="break-all font-mono text-sm font-semibold">{{ trace.summary.title }}</div>
                <div class="mt-1 text-xs text-gray-500">
                    <template v-if="trace.summary.subtitle">{{ trace.summary.subtitle }} · </template>
                    <span :class="trace.summary.failed ? 'font-semibold text-rose-600' : ''">{{ trace.summary.status }}</span> ·
                    {{ formatMicroseconds(trace.summary.duration) }} · {{ (trace.summary.peak_memory_usage / 1048576).toFixed(0) }} MB · {{ trace.summary.created_at }} UTC
                    <template v-if="trace.summary.user_id"> · {{ ctrans("user") }} {{ trace.summary.user_id }}</template>
                </div>
                <div class="mt-1 flex flex-wrap gap-3 text-xs">
                    <span v-for="(typeTotal, type) in counts" :key="type" class="flex items-center gap-1">
                        <span class="inline-block h-2 w-2 rounded-sm" :class="colours[type]" />
                        {{ typeLabels[type] }} {{ typeTotal?.count }} · {{ formatMicroseconds(typeTotal?.time) }}
                    </span>
                </div>
                <div v-if="trace.truncated" class="mt-1 text-xs text-amber-700">{{ ctrans("Showing the first 500 of each kind of event") }}</div>
                <div v-if="trace.summary.exception_preview" class="mt-1 text-xs text-rose-700">{{ trace.summary.exception_preview }}</div>
            </div>
            <button class="text-sm text-gray-500 hover:text-gray-800" @click="$emit('close')">{{ ctrans("Close") }}</button>
        </div>

        <div class="max-h-[36rem] overflow-y-auto">
            <div class="grid grid-cols-[minmax(0,2fr)_minmax(0,3fr)_5rem] items-center gap-x-3 gap-y-1 text-xs">
                <template v-for="stage in stages" :key="stage.label">
                    <div class="truncate font-medium text-gray-600">{{ stage.label }}</div>
                    <div class="relative h-3 rounded bg-gray-50"><div class="absolute top-0 h-3 rounded" :class="stage.colour" :style="position(stage.offset, stage.duration)" /></div>
                    <div class="text-right tabular-nums text-gray-500">{{ formatMicroseconds(stage.duration) }}</div>
                </template>
                <div v-if="stages.length" class="col-span-3 my-1 border-t" />
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
                <div v-if="!trace.spans.length" class="col-span-3 py-2 text-gray-500">{{ ctrans("No queries, cache events or outgoing requests were recorded") }}</div>
            </div>
        </div>
    </div>
</template>
