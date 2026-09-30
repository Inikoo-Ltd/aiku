<!--
  - Author Louis Perez
  - Created on 29-09-2026-13h-18m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faInfoCircle, faCloudRainbow, faBox, faBoxesAlt } from "@fal"
library.add(faInfoCircle, faCloudRainbow, faBox, faBoxesAlt)
import { useFormatTime } from "@/Composables/useFormatTime"
import { computed } from "vue"
import type { QuickLookHistoryEntry, QuickLookHistoryRecord, QuickLookHistoryRecordResolver } from "@/types/QuickLookCatalogue"

const props = defineProps<{
    entries: QuickLookHistoryEntry[]
    fullHistoryUrl: string | null
    notice?: string | null
    recordResolver?: QuickLookHistoryRecordResolver
}>()

const records = computed<(QuickLookHistoryRecord | null)[]>(() => props.entries.map((entry) => props.recordResolver?.(entry) ?? null))

const asRecord = (values: QuickLookHistoryEntry["new_values"]): Record<string, unknown> => (values && !Array.isArray(values) ? values : {})

const changes = (entry: QuickLookHistoryEntry): { field: string; from: unknown; to: unknown }[] => {
    const oldValues = asRecord(entry.old_values)
    const newValues = asRecord(entry.new_values)

    if (entry.event === "state_change") {
        const rows = [{ field: ctrans("Status"), from: oldValues.state, to: newValues.to_state ?? newValues.state }]
        if (newValues.reason) rows.push({ field: ctrans("Reason"), from: undefined, to: newValues.reason })
        return rows
    }

    return [...new Set([...Object.keys(oldValues), ...Object.keys(newValues)])].map((field) => ({ field, from: oldValues[field], to: newValues[field] }))
}

const eventLabel = (event: string): string => (event === "state_change" ? ctrans("Status change") : event)

const display = (value: unknown): string => {
    if (value === null || value === undefined || value === "") return "—"
    if (typeof value === "object") return JSON.stringify(value)
    return String(value)
}
</script>

<template>
    <div class="px-6 py-4">
        <div v-if="notice" class="mb-3 flex items-start gap-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800" role="note">
            <FontAwesomeIcon icon="fal fa-info-circle" class="mt-0.5" fixed-width aria-hidden="true" />
            <span>{{ notice }}</span>
        </div>
        <div v-if="!entries.length" class="py-16 text-center text-sm text-gray-500">{{ ctrans("No history recorded yet.") }}</div>
        <ol v-else class="divide-y divide-gray-100">
            <li v-for="(entry, index) in entries" :key="entry.id" class="py-3 text-sm">
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <span class="rounded bg-gray-100 px-1.5 py-0.5 text-xs font-semibold uppercase text-gray-600">{{ eventLabel(entry.event) }}</span>
                    <component
                        :is="records[index]?.url ? 'a' : 'span'"
                        v-if="entry.record"
                        v-tooltip="records[index]?.tooltip"
                        :href="records[index]?.url ?? undefined"
                        :target="records[index]?.url ? '_blank' : undefined"
                        :rel="records[index]?.url ? 'noopener' : undefined"
                        class="inline-flex items-center gap-1 rounded border border-gray-200 px-1.5 py-0.5 text-xs font-medium text-gray-600"
                        :class="{ 'transition-colors hover:border-[--app-accent] hover:text-[--app-accent]': records[index]?.url }"
                    >
                        <FontAwesomeIcon :icon="records[index]?.icon ?? 'fal fa-box'" :class="{ hidden: !records[index] }" fixed-width aria-hidden="true" />
                        {{ entry.record }}
                    </component>
                    <span class="font-medium text-gray-900">{{ entry.user_name }}</span>
                    <span class="ml-auto text-xs tabular-nums text-gray-500">{{ useFormatTime(entry.datetime, { formatTime: "PPp" }) }}</span>
                </div>
                <dl v-if="changes(entry).length" class="mt-1.5 grid grid-cols-[minmax(0,10rem)_minmax(0,1fr)] gap-x-3 gap-y-0.5 text-xs">
                    <template v-for="change in changes(entry)" :key="change.field">
                        <dt class="truncate text-gray-500">{{ change.field }}</dt>
                        <dd class="min-w-0 break-words text-gray-700">
                            <template v-if="change.from !== undefined">
                                <span class="text-gray-400 line-through">{{ display(change.from) }}</span>
                                <span class="mx-1 text-gray-400">→</span>
                            </template>
                            <span>{{ display(change.to) }}</span>
                        </dd>
                    </template>
                </dl>
            </li>
        </ol>
        <a
            v-if="fullHistoryUrl && entries.length"
            :href="fullHistoryUrl"
            target="_blank"
            rel="noopener"
            class="mt-2 inline-block rounded px-1.5 py-0.5 text-sm font-medium text-[--app-accent] transition-colors hover:bg-[--app-accent-soft]"
        >
            {{ ctrans("Open full history") }} →
        </a>
    </div>
</template>
