<!--
 Author Louis Perez
 Created on 14-09-2026-14h-23m
 GitHub: https://github.com/louis-perez
 Copyright 2026
-->

<script lang="ts">
export type TicketAttachment = { name: string; url: string; size?: number; mime?: string | null; created_at?: string | null; thumbnail?: Record<string, string> | null }

const extensionOf = (file: TicketAttachment) => file.name.split(".").pop()?.toLowerCase() ?? ""

export const isPdfAttachment = (file: TicketAttachment) => file.mime === "application/pdf" || extensionOf(file) === "pdf"

export const isWordAttachment = (file: TicketAttachment) => extensionOf(file) === "docx"

export const isSpreadsheetAttachment = (file: TicketAttachment) => ["xls", "xlsx", "csv"].includes(extensionOf(file))

export const isVideoAttachment = (file: TicketAttachment) => (file.mime ?? "").startsWith("video/") || ["mp4", "webm", "mov"].includes(extensionOf(file))

export const isImageAttachment = (file: TicketAttachment) => (file.mime ?? "").startsWith("image/") || ["jpg", "jpeg", "png", "gif", "webp", "bmp", "svg"].includes(extensionOf(file))

export const isPreviewableAttachment = (file: TicketAttachment) => isImageAttachment(file) || isPdfAttachment(file) || isWordAttachment(file) || isSpreadsheetAttachment(file) || isVideoAttachment(file)
</script>

<script setup lang="ts">
import { computed, nextTick, ref, shallowRef, watch, onBeforeUnmount } from "vue"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTimes, faChevronLeft, faChevronRight, faExternalLink, faSpinner } from "@fal"

library.add(faTimes, faChevronLeft, faChevronRight, faExternalLink, faSpinner)

const props = defineProps<{
    files: TicketAttachment[]
}>()

const index = defineModel<number | null>("index", { default: null })

const currentFile = computed(() => (index.value === null ? null : props.files[index.value] ?? null))

const previewState = ref<"loading" | "ready" | "unavailable">("loading")
const wordContainer = ref<HTMLElement | null>(null)
const sheetNames = ref<string[]>([])
const activeSheet = ref("")
const renderSheet = shallowRef<((sheetName: string) => string) | null>(null)

const sheetHtml = computed(() => (renderSheet.value && activeSheet.value ? renderSheet.value(activeSheet.value) : ""))

const isStillCurrent = (url: string) => currentFile.value?.url === url

const loadPreview = async (file: TicketAttachment) => {
    const url = file.url
    previewState.value = "loading"
    renderSheet.value = null
    sheetNames.value = []

    try {
        if (isImageAttachment(file) || isPdfAttachment(file) || isVideoAttachment(file)) {
            const response = await fetch(url, { method: "HEAD" })
            if (isStillCurrent(url)) previewState.value = response.ok ? "ready" : "unavailable"
            return
        }

        const response = await fetch(url)
        if (!response.ok) throw new Error(response.statusText)

        if (isWordAttachment(file)) {
            const [{ renderAsync }, document] = await Promise.all([import("docx-preview"), response.blob()])
            if (!isStillCurrent(url)) return
            previewState.value = "ready"
            await nextTick()
            if (!wordContainer.value) return
            wordContainer.value.innerHTML = ""
            await renderAsync(document, wordContainer.value, undefined, { inWrapper: true, ignoreLastRenderedPageBreak: true })
            return
        }

        const XLSX = await import("xlsx")
        const workbook = extensionOf(file) === "csv"
            ? XLSX.read(await response.text(), { type: "string" })
            : XLSX.read(new Uint8Array(await response.arrayBuffer()), { type: "array" })
        if (!isStillCurrent(url)) return
        renderSheet.value = (sheetName) => XLSX.utils.sheet_to_html(workbook.Sheets[sheetName], { header: "", footer: "" })
        sheetNames.value = workbook.SheetNames
        activeSheet.value = workbook.SheetNames[0] ?? ""
        previewState.value = "ready"
    } catch {
        if (isStillCurrent(url)) previewState.value = "unavailable"
    }
}

watch(
    () => currentFile.value?.url,
    () => {
        if (currentFile.value) loadPreview(currentFile.value)
    }
)

const close = () => {
    index.value = null
}

const previous = () => {
    if (index.value === null || !props.files.length) return
    index.value = (index.value - 1 + props.files.length) % props.files.length
}

const next = () => {
    if (index.value === null || !props.files.length) return
    index.value = (index.value + 1) % props.files.length
}

const onKeydown = (event: KeyboardEvent) => {
    if (event.key === "Escape") close()
    else if (event.key === "ArrowLeft") previous()
    else if (event.key === "ArrowRight") next()
}

watch(
    () => currentFile.value !== null,
    (isOpen) => {
        if (isOpen) window.addEventListener("keydown", onKeydown)
        else window.removeEventListener("keydown", onKeydown)
    }
)

onBeforeUnmount(() => window.removeEventListener("keydown", onKeydown))
</script>

<template>
    <Teleport to="body">
        <div v-if="currentFile" class="fixed inset-0 z-[9999] flex flex-col items-center justify-center bg-black/80 px-16 py-4" @click.self="close">
            <div class="mb-2 flex w-full max-w-5xl items-center justify-between gap-4 text-white">
                <span class="truncate text-sm">{{ currentFile.name }}</span>
                <div class="flex shrink-0 items-center gap-4">
                    <span v-if="files.length > 1" class="text-sm text-white/80">{{ (index ?? 0) + 1 }} / {{ files.length }}</span>
                    <a :href="currentFile.url" target="_blank" rel="noopener" v-tooltip="trans('Open in new tab')" class="text-2xl text-white/80 hover:text-white">
                        <FontAwesomeIcon icon="fal fa-external-link" fixed-width />
                    </a>
                    <button type="button" class="text-3xl text-white/80 hover:text-white" @click="close">
                        <FontAwesomeIcon icon="fal fa-times" fixed-width />
                    </button>
                </div>
            </div>
            <button v-if="files.length > 1" type="button" class="absolute left-2 top-1/2 -translate-y-1/2 p-3 text-4xl text-white/80 hover:text-white" @click="previous">
                <FontAwesomeIcon icon="fal fa-chevron-left" fixed-width />
            </button>
            <div v-if="previewState === 'loading'" class="flex h-[85vh] w-full max-w-5xl items-center justify-center rounded bg-white text-sm text-gray-500">
                <FontAwesomeIcon icon="fal fa-spinner" spin class="mr-2" />{{ trans("Loading") }}
            </div>
            <div v-else-if="previewState === 'unavailable'" class="flex h-[85vh] w-full max-w-5xl items-center justify-center rounded bg-white text-sm text-gray-500">
                {{ trans("Preview for this file is unavailable") }}
            </div>
            <img v-else-if="isImageAttachment(currentFile)" :key="currentFile.url" :src="currentFile.url" :alt="currentFile.name" class="max-h-[85vh] max-w-full rounded object-contain" @error="previewState = 'unavailable'" />
            <iframe v-else-if="isPdfAttachment(currentFile)" :key="currentFile.url" :src="currentFile.url" :title="currentFile.name" class="h-[85vh] w-full max-w-5xl rounded bg-white" />
            <video v-else-if="isVideoAttachment(currentFile)" :key="currentFile.url" :src="currentFile.url" controls playsinline preload="metadata" class="max-h-[85vh] w-full max-w-5xl rounded bg-black" @error="previewState = 'unavailable'" />
            <div v-else-if="isWordAttachment(currentFile)" ref="wordContainer" :key="currentFile.url" class="h-[85vh] w-full max-w-5xl overflow-auto rounded bg-gray-100" />
            <div v-else class="flex h-[85vh] w-full max-w-5xl flex-col overflow-hidden rounded bg-white">
                <div v-if="sheetNames.length > 1" class="flex shrink-0 gap-1 overflow-x-auto border-b border-gray-200 bg-gray-50 px-2 pt-2">
                    <button
                        v-for="sheetName in sheetNames"
                        :key="sheetName"
                        type="button"
                        class="whitespace-nowrap rounded-t border border-b-0 px-3 py-1 text-xs"
                        :class="sheetName === activeSheet ? 'border-gray-300 bg-white font-semibold text-gray-800' : 'border-transparent text-gray-500 hover:text-gray-800'"
                        @click="activeSheet = sheetName"
                    >
                        {{ sheetName }}
                    </button>
                </div>
                <div class="spreadsheet-preview flex-1 overflow-auto p-2 text-xs" v-html="sheetHtml" />
            </div>
            <button v-if="files.length > 1" type="button" class="absolute right-2 top-1/2 -translate-y-1/2 p-3 text-4xl text-white/80 hover:text-white" @click="next">
                <FontAwesomeIcon icon="fal fa-chevron-right" fixed-width />
            </button>
        </div>
    </Teleport>
</template>

<style scoped>
.spreadsheet-preview :deep(table) { border-collapse: collapse; }
.spreadsheet-preview :deep(td) { border: 1px solid rgb(229 231 235); padding: 0.25rem 0.5rem; white-space: nowrap; color: rgb(31 41 55); }
.spreadsheet-preview :deep(tr:first-child td) { background: rgb(249 250 251); font-weight: 600; }
</style>
