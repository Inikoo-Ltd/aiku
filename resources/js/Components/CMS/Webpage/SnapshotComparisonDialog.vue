<!--
  - Author: Artha <artha@aw-advantage.com>
  - Created: Wed, 11 Nov 2026
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import axios from "axios"
import Dialog from "primevue/dialog"
import Select from "primevue/select"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"

type Segment = { type: "equal" | "added" | "removed"; text: string }
type DiffRow = { type: "equal" | "changed" | "added" | "removed"; segments: Segment[] }
type FieldChange = { field: string; kind: "text" | "html" | "image" | "value"; before: string; after: string; rows: DiffRow[] }
type BlockChange = { key: string; name: string; status: "added" | "removed" | "changed"; fields: FieldChange[] }
type SnapshotSummary = { id: number; state: string; published_at: string | null; edited_at?: string | null; publisher: string | null; comment?: string | null }
type Comparison = { from: SnapshotSummary | null; to: SnapshotSummary; options: SnapshotSummary[]; blocks: BlockChange[] }

const props = defineProps<{
    webpageId: number
    snapshotId: number | null
}>()

const visible = defineModel<boolean>("visible", { default: false })

const locale = useLocaleStore()
const comparison = ref<Comparison | null>(null)
const fromId = ref<number | null>(null)
const toId = ref<number | null>(null)
const isLoading = ref(false)
const errorMessage = ref<string | null>(null)
const expandedRows = ref<Record<string, boolean>>({})

const formatDate = (date: string | null | undefined) =>
    date ? useFormatTime(date, { localeCode: locale.language.code, formatTime: "hm" }) : ""

const snapshotLabel = (snapshot: SnapshotSummary) => {
    if (snapshot.state === "unpublished") {
        return ctrans("Unpublished changes")
    }
    const label = [formatDate(snapshot.published_at), snapshot.publisher].filter(Boolean).join(" · ")
    return snapshot.state === "live" ? `${label} (${ctrans("live")})` : label
}

const fromOptions = computed(() => (comparison.value?.options ?? []).filter((option) => option.id !== toId.value))

const fetchComparison = async (params: { to: number; from?: number | null }) => {
    isLoading.value = true
    errorMessage.value = null
    try {
        const response = await axios.get(route("grp.json.webpage.snapshots_comparison", { webpage: props.webpageId }), {
            params: params.from ? params : { to: params.to },
        })
        comparison.value = response.data
        toId.value = response.data.to.id
        fromId.value = response.data.from?.id ?? null
        expandedRows.value = {}
    } catch (error: any) {
        console.error("Snapshot comparison failed", error)
        const detail = [error?.response?.status, error?.response?.data?.message || error?.message].filter(Boolean).join(": ")
        errorMessage.value = ctrans("The versions could not be compared") + (detail ? ` (${detail})` : "")
    } finally {
        isLoading.value = false
    }
}

watch(
    () => [visible.value, props.snapshotId],
    () => {
        if (visible.value && props.snapshotId) {
            fetchComparison({ to: props.snapshotId })
        }
    },
    { immediate: true }
)

const onSelectTo = (value: number) => fetchComparison({ to: value, from: value === fromId.value ? null : fromId.value })
const onSelectFrom = (value: number) => toId.value && fetchComparison({ to: toId.value, from: value })

const fieldLabel = (field: string) => {
    if (field === "visible") {
        return ctrans("Visibility")
    }
    return field
        .split(".")
        .filter((segment) => !/^\d+$/.test(segment))
        .map((segment) => segment.replace(/_/g, " ").replace(/([a-z])([A-Z])/g, "$1 $2").toLowerCase())
        .map((segment) => segment.charAt(0).toUpperCase() + segment.slice(1))
        .join(" › ")
}

const statusLabel: Record<BlockChange["status"], string> = {
    added: ctrans("Added"),
    removed: ctrans("Removed"),
    changed: ctrans("Changed"),
}

const statusClass: Record<BlockChange["status"], string> = {
    added: "bg-green-50 text-green-700 ring-green-200",
    removed: "bg-red-50 text-red-700 ring-red-200",
    changed: "bg-amber-50 text-amber-700 ring-amber-200",
}

type DisplayRow = { kind: "row"; row: DiffRow; index: number } | { kind: "collapsed"; count: number; start: number }

const displayRows = (blockKey: string, field: FieldChange): DisplayRow[] => {
    const rowKey = `${blockKey}:${field.field}`
    if (expandedRows.value[rowKey]) {
        return field.rows.map((row, index) => ({ kind: "row", row, index }))
    }
    const isChange = (index: number) => !!field.rows[index] && field.rows[index].type !== "equal"
    const keep = field.rows.map((row, index) => isChange(index) || isChange(index - 1) || isChange(index + 1))
    const result: DisplayRow[] = []
    let hidden = 0
    let hiddenStart = 0
    field.rows.forEach((row, index) => {
        if (keep[index]) {
            if (hidden) {
                result.push({ kind: "collapsed", count: hidden, start: hiddenStart })
                hidden = 0
            }
            result.push({ kind: "row", row, index })
        } else {
            if (!hidden) {
                hiddenStart = index
            }
            hidden++
        }
    })
    if (hidden) {
        result.push({ kind: "collapsed", count: hidden, start: hiddenStart })
    }
    return result
}

const segmentClass = (segment: Segment) => ({
    "bg-green-100 text-green-900": segment.type === "added",
    "bg-red-100 text-red-800 line-through decoration-red-300": segment.type === "removed",
})

const rowClass = (row: DiffRow) => ({
    "border-green-400": row.type === "added",
    "border-red-400": row.type === "removed",
    "border-amber-400": row.type === "changed",
    "border-transparent text-gray-500": row.type === "equal",
})
</script>

<template>
    <Dialog v-model:visible="visible" modal :header="ctrans('What changed')" class="w-full max-w-4xl" :dismissableMask="true">
        <div class="space-y-4 text-sm">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <label class="block">
                    <span class="text-xs font-semibold text-gray-600">{{ ctrans("Before") }}</span>
                    <Select
                        :modelValue="fromId"
                        :options="fromOptions"
                        optionValue="id"
                        :optionLabel="snapshotLabel"
                        :placeholder="ctrans('Nothing (first version)')"
                        :disabled="isLoading || !comparison"
                        class="mt-1 w-full"
                        @update:modelValue="onSelectFrom"
                    />
                </label>
                <label class="block">
                    <span class="text-xs font-semibold text-gray-600">{{ ctrans("After") }}</span>
                    <Select
                        :modelValue="toId"
                        :options="comparison?.options ?? []"
                        optionValue="id"
                        :optionLabel="snapshotLabel"
                        :disabled="isLoading || !comparison"
                        class="mt-1 w-full"
                        @update:modelValue="onSelectTo"
                    />
                </label>
            </div>

            <div v-if="isLoading && !comparison" class="space-y-2">
                <div class="skeleton h-14 rounded" />
                <div class="skeleton h-32 rounded" />
            </div>

            <p v-else-if="errorMessage" class="text-xs text-red-600">{{ errorMessage }}</p>

            <div v-else-if="comparison" class="space-y-4" :class="{ 'opacity-60 pointer-events-none': isLoading }">
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-500">
                    <LoadingIcon v-if="isLoading" />
                    <template v-if="comparison.to.state === 'unpublished'">
                        {{ ctrans("Unpublished changes, last saved :date", { date: formatDate(comparison.to.edited_at) }) }}
                    </template>
                    <template v-else>
                        {{ ctrans("Published by :publisher on :date", { publisher: comparison.to.publisher || ctrans("System"), date: formatDate(comparison.to.published_at) }) }}
                    </template>
                    <span v-if="comparison.to.comment" class="italic">“{{ comparison.to.comment }}”</span>
                </div>

                <div v-if="!comparison.blocks.length" class="rounded border border-gray-200 px-4 py-6 text-center text-gray-500">
                    {{ ctrans("The content is the same in both versions") }}
                </div>

                <section v-for="block in comparison.blocks" :key="block.key" class="rounded border border-gray-200">
                    <header class="flex items-center gap-2 border-b border-gray-200 bg-gray-50 px-3 py-2">
                        <span class="font-semibold text-gray-700">{{ block.name }}</span>
                        <span class="rounded px-1.5 py-0.5 text-[11px] font-medium ring-1" :class="statusClass[block.status]">
                            {{ statusLabel[block.status] }}
                        </span>
                    </header>

                    <div class="divide-y divide-gray-100">
                        <div v-for="field in block.fields" :key="field.field" class="grid grid-cols-1 gap-2 px-3 py-2 md:grid-cols-[10rem_minmax(0,1fr)]">
                            <div class="text-xs font-medium text-gray-500 break-words">{{ fieldLabel(field.field) }}</div>

                            <div v-if="field.kind === 'image'" class="flex flex-wrap items-center gap-3">
                                <div v-if="field.before" class="rounded ring-2 ring-red-300 p-0.5">
                                    <img :src="field.before" alt="" class="h-20 w-auto rounded" />
                                </div>
                                <span v-if="field.before && field.after" class="text-gray-400">→</span>
                                <div v-if="field.after" class="rounded ring-2 ring-green-300 p-0.5">
                                    <img :src="field.after" alt="" class="h-20 w-auto rounded" />
                                </div>
                            </div>

                            <div v-else class="space-y-1 text-gray-800">
                                <template v-for="item in displayRows(block.key, field)" :key="item.kind === 'row' ? `r${item.index}` : `c${item.start}`">
                                    <button
                                        v-if="item.kind === 'collapsed'"
                                        type="button"
                                        class="text-xs text-[var(--app-accent)] hover:underline"
                                        @click="expandedRows[`${block.key}:${field.field}`] = true"
                                    >
                                        {{ ctrans(":count unchanged paragraphs", { count: item.count }) }}
                                    </button>
                                    <p v-else class="whitespace-pre-wrap break-words border-l-2 pl-2" :class="rowClass(item.row)">
                                        <span v-for="(segment, segmentIndex) in item.row.segments" :key="segmentIndex" :class="segmentClass(segment)">{{ segment.text }}</span>
                                    </p>
                                </template>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </Dialog>
</template>
