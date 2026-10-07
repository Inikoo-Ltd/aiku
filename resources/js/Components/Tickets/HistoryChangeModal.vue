<!--
  - Author: aqordeon <dev@aw-advantage.com>
  - Created: Mon, 05 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Inikoo Ltd
  -->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { Dialog } from "primevue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { wordDiff } from "@/Composables/useWordDiff"

const props = defineProps<{
    event: { at: string; by: string | null; change: { label: string; from: string; to: string } } | null
}>()

const emit = defineEmits<{
    (e: "close"): void
}>()

const isSideBySide = ref(false)
watch(() => props.event, () => (isSideBySide.value = false))

const parts = computed(() => (props.event ? wordDiff(props.event.change.from, props.event.change.to) : null))
const showsSideBySide = computed(() => isSideBySide.value || parts.value === null)
</script>

<template>
    <Dialog :visible="event !== null" modal :header="event ? ctrans(':label changed', { label: event.change.label }) : ''" :style="{ width: '48rem' }" :breakpoints="{ '768px': '95vw' }" @update:visible="(visible) => !visible && emit('close')">
        <div v-if="event" class="space-y-3 text-sm">
            <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-gray-500">
                <span>{{ useFormatTime(event.at, { formatTime: "hm" }) }}<template v-if="event.by"> · {{ event.by }}</template></span>
                <span class="flex items-center gap-3">
                    <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-sm bg-red-200" aria-hidden="true" />{{ ctrans("Removed") }}</span>
                    <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-sm bg-green-200" aria-hidden="true" />{{ ctrans("Added") }}</span>
                    <button v-if="parts !== null" type="button" class="rounded px-2 py-1 text-[--app-accent-strong] transition duration-200 hover:bg-gray-100" @click="isSideBySide = !isSideBySide">
                        {{ isSideBySide ? ctrans("Show changes inline") : ctrans("Show before and after") }}
                    </button>
                </span>
            </div>

            <div v-if="!showsSideBySide" class="max-h-[60vh] overflow-y-auto whitespace-pre-wrap break-words rounded-md border border-gray-200 bg-gray-50 p-3 leading-relaxed text-gray-800">
                <template v-for="(part, index) in parts" :key="index">
                    <del v-if="part.type === 'removed'" class="rounded-sm bg-red-100 text-red-800 decoration-red-400">{{ part.text }}</del>
                    <ins v-else-if="part.type === 'added'" class="rounded-sm bg-green-100 text-green-800 no-underline">{{ part.text }}</ins>
                    <span v-else>{{ part.text }}</span>
                </template>
            </div>

            <div v-else class="grid gap-3 md:grid-cols-2">
                <div>
                    <p class="mb-1 text-xs font-medium uppercase tracking-wide text-gray-400">{{ ctrans("Before") }}</p>
                    <div class="max-h-[55vh] overflow-y-auto whitespace-pre-wrap break-words rounded-md border border-red-100 bg-red-50/60 p-3 leading-relaxed text-gray-800">
                        <template v-if="event.change.from">{{ event.change.from }}</template>
                        <span v-else class="italic text-gray-400">{{ ctrans("Empty") }}</span>
                    </div>
                </div>
                <div>
                    <p class="mb-1 text-xs font-medium uppercase tracking-wide text-gray-400">{{ ctrans("After") }}</p>
                    <div class="max-h-[55vh] overflow-y-auto whitespace-pre-wrap break-words rounded-md border border-green-100 bg-green-50/60 p-3 leading-relaxed text-gray-800">
                        <template v-if="event.change.to">{{ event.change.to }}</template>
                        <span v-else class="italic text-gray-400">{{ ctrans("Empty") }}</span>
                    </div>
                </div>
            </div>
        </div>
    </Dialog>
</template>
