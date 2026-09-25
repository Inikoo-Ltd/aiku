<!--
 Author Louis Perez
 Created on 14-09-2026-14h-23m
 GitHub: https://github.com/louis-perez
 Copyright 2026
-->

<script lang="ts">
import { ctrans as translate } from "@/Composables/useTrans"

export const reasonForResponse = async (response: Response) => {
    if (response.status === 401 || response.status === 419) return translate("Your session has expired. Reload the page and try again.")
    if (response.status === 403) return translate("You do not have permission to view this file.")
    if (response.status === 404) return translate("This file could not be found. It may have been deleted, or it is not part of this ticket.")
    if (response.status === 422) {
        const body = await response.json().catch(() => null)
        return body?.message ?? translate("This file cannot be read.")
    }
    if (response.status >= 500) return translate("The server could not load this file. Please try again later.")
    return translate("The file could not be loaded (error :status).", { status: String(response.status) })
}

export const reasonFileIsUnavailable = async (url: string) => {
    if (url.startsWith("blob:")) return translate("This file could not be displayed. It may be damaged or in a format your browser cannot show.")

    try {
        const response = await fetch(url, { method: "HEAD" })
        return response.ok ? translate("This file could not be displayed. It may be damaged or in a format your browser cannot show.") : await reasonForResponse(response)
    } catch {
        return translate("Could not reach the server. Check your connection and try again.")
    }
}

export type TicketAttachment = { name: string; url: string; size?: number; mime?: string | null; created_at?: string | null; thumbnail?: Record<string, string> | null }

const extensionOf = (file: TicketAttachment) => file.name.split(".").pop()?.toLowerCase() ?? ""

export const isPdfAttachment = (file: TicketAttachment) => file.mime === "application/pdf" || extensionOf(file) === "pdf"

export const isWordAttachment = (file: TicketAttachment) => extensionOf(file) === "docx"

export const isSpreadsheetAttachment = (file: TicketAttachment) => ["xls", "xlsx", "csv"].includes(extensionOf(file))

export const isVideoAttachment = (file: TicketAttachment) => (file.mime ?? "").startsWith("video/") || ["mp4", "webm", "mov"].includes(extensionOf(file))

export const isArchiveAttachment = (file: TicketAttachment) => ["zip", "rar", "7z"].includes(extensionOf(file))

export const isImageAttachment = (file: TicketAttachment) => (file.mime ?? "").startsWith("image/") || ["jpg", "jpeg", "png", "gif", "webp", "bmp", "svg"].includes(extensionOf(file))

export const isPreviewableAttachment = (file: TicketAttachment) => isImageAttachment(file) || isPdfAttachment(file) || isWordAttachment(file) || isSpreadsheetAttachment(file) || isVideoAttachment(file) || isArchiveAttachment(file)
</script>

<script setup lang="ts">
import { computed, nextTick, ref, shallowRef, watch, onBeforeUnmount } from "vue"
import { ctrans } from "@/Composables/useTrans"
import { useModalFocusTrap } from "@/Composables/useModalFocusTrap"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTimes, faChevronLeft, faChevronRight, faExternalLink, faSpinner, faDownload, faFolder, faFile } from "@fal"

library.add(faTimes, faChevronLeft, faChevronRight, faExternalLink, faSpinner, faDownload, faFolder, faFile)

const props = defineProps<{
    files: TicketAttachment[]
}>()

const index = defineModel<number | null>("index", { default: null })

const currentFile = computed(() => (index.value === null ? null : props.files[index.value] ?? null))

const overlay = ref<HTMLElement | null>(null)

useModalFocusTrap(computed(() => currentFile.value !== null), overlay)

const previewState = ref<"loading" | "ready" | "unavailable">("loading")
const unavailableReason = ref("")

class PreviewUnavailable extends Error {
    constructor(public reason: string) {
        super(reason)
    }
}

const markUnavailable = (reason: string) => {
    unavailableReason.value = reason
    previewState.value = "unavailable"
}

const markUndisplayable = () => markUnavailable(ctrans("This file could not be displayed. It may be damaged or in a format your browser cannot show."))
const wordContainer = ref<HTMLElement | null>(null)
const sheetNames = ref<string[]>([])
const activeSheet = ref("")
const renderSheet = shallowRef<((sheetName: string) => string) | null>(null)

type ZipEntry = { name: string; size: number; is_directory: boolean }
const zipContents = ref<{ total: number; entries: ZipEntry[] } | null>(null)

const sortedZipEntries = computed(() => [...(zipContents.value?.entries ?? [])].sort((first, second) => first.name.localeCompare(second.name)))
const zipFileCount = computed(() => sortedZipEntries.value.filter((entry) => !entry.is_directory).length)
const zipTotalSize = computed(() => sortedZipEntries.value.reduce((total, entry) => total + entry.size, 0))

const depthOf = (entryName: string) => entryName.replace(/\/$/, "").split("/").length - 1
const baseNameOf = (entryName: string) => entryName.replace(/\/$/, "").split("/").pop() ?? entryName
const formatSize = (bytes: number) => {
    if (bytes < 1024) return `${bytes} B`
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

const sheetHtml = computed(() => (renderSheet.value && activeSheet.value ? renderSheet.value(activeSheet.value) : ""))

const isStillCurrent = (url: string) => currentFile.value?.url === url

const loadPreview = async (file: TicketAttachment) => {
    const url = file.url
    previewState.value = "loading"
    unavailableReason.value = ""
    renderSheet.value = null
    sheetNames.value = []
    zipContents.value = null

    try {
        if (isImageAttachment(file) || isPdfAttachment(file) || isVideoAttachment(file)) {
            if (!url.startsWith("blob:")) {
                const response = await fetch(url, { method: "HEAD" })
                if (!response.ok) throw new PreviewUnavailable(await reasonForResponse(response))
            }
            if (isStillCurrent(url)) previewState.value = "ready"
            return
        }

        if (isArchiveAttachment(file)) {
            const contentsUrl = new URL(url, window.location.origin)
            contentsUrl.searchParams.set("contents", "1")
            const contentsResponse = await fetch(contentsUrl, { headers: { Accept: "application/json" } })
            if (!contentsResponse.ok) throw new PreviewUnavailable(await reasonForResponse(contentsResponse))
            const contents = await contentsResponse.json()
            if (!isStillCurrent(url)) return
            zipContents.value = contents
            previewState.value = "ready"
            return
        }

        const response = await fetch(url)
        if (!response.ok) throw new PreviewUnavailable(await reasonForResponse(response))

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
    } catch (error) {
        if (!isStillCurrent(url)) return
        if (error instanceof PreviewUnavailable) markUnavailable(error.reason)
        else if (error instanceof TypeError && error.message.toLowerCase().includes("fetch")) markUnavailable(ctrans("Could not reach the server. Check your connection and try again."))
        else markUnavailable(ctrans("This file could not be read. It may be damaged or in an unsupported format."))
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
        <div v-if="currentFile" ref="overlay" tabindex="-1" class="fixed inset-0 z-[9999] flex flex-col items-center justify-center overscroll-contain bg-black/80 px-16 py-4 outline-none" @click.self="close">
            <div class="mb-2 flex w-full max-w-5xl items-center justify-between gap-4 text-white">
                <span class="truncate text-sm">{{ currentFile.name }}</span>
                <div class="flex shrink-0 items-center gap-4">
                    <span v-if="files.length > 1" class="text-sm text-white/80">{{ (index ?? 0) + 1 }} / {{ files.length }}</span>
                    <a :href="currentFile.url" target="_blank" rel="noopener" v-tooltip="ctrans('Open in new tab')" class="text-2xl text-white/80 hover:text-white">
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
                <FontAwesomeIcon icon="fal fa-spinner" spin class="mr-2" fixed-width />{{ ctrans("Loading") }}
            </div>
            <div v-else-if="previewState === 'unavailable'" class="flex h-[85vh] w-full max-w-5xl items-center justify-center rounded bg-white text-sm text-gray-500">
                <div class="max-w-md px-6 text-center">
                    <p class="font-medium text-gray-700">{{ ctrans("Preview for this file is unavailable") }}</p>
                    <p v-if="unavailableReason" class="mt-1 text-gray-500">{{ unavailableReason }}</p>
                </div>
            </div>
            <img v-else-if="isImageAttachment(currentFile)" :key="currentFile.url" :src="currentFile.url" :alt="currentFile.name" class="max-h-[85vh] max-w-full rounded object-contain" @error="markUndisplayable" />
            <iframe v-else-if="isPdfAttachment(currentFile)" :key="currentFile.url" :src="currentFile.url" :title="currentFile.name" class="h-[85vh] w-full max-w-5xl rounded bg-white" />
            <video v-else-if="isVideoAttachment(currentFile)" :key="currentFile.url" :src="currentFile.url" controls playsinline preload="metadata" class="max-h-[85vh] w-full max-w-5xl rounded bg-black" @error="markUndisplayable" />
            <div v-else-if="isWordAttachment(currentFile)" ref="wordContainer" :key="currentFile.url" class="h-[85vh] w-full max-w-5xl overflow-auto rounded bg-gray-100" />
            <div v-else-if="isArchiveAttachment(currentFile)" :key="currentFile.url" class="flex h-[85vh] w-full max-w-5xl flex-col overflow-hidden rounded bg-white">
                <div class="flex shrink-0 items-center justify-between gap-4 border-b border-gray-200 bg-gray-50 px-4 py-2 text-xs text-gray-500">
                    <span class="tabular-nums">{{ ctrans(":count files", { count: String(zipFileCount) }) }} · {{ formatSize(zipTotalSize) }}</span>
                    <a :href="currentFile.url" :download="currentFile.name" class="inline-flex items-center gap-1.5 rounded-md bg-[--app-accent] px-3 py-1.5 text-xs font-medium text-[--app-accent-text] transition duration-200 hover:bg-[--app-accent-deep] focus:!bg-[--app-accent-deep]">
                        <FontAwesomeIcon icon="fal fa-download" fixed-width />{{ ctrans("Download") }}
                    </a>
                </div>
                <ul class="flex-1 overflow-auto py-1 text-sm text-gray-700">
                    <li
                        v-for="entry in sortedZipEntries"
                        :key="entry.name"
                        class="flex items-center gap-2 py-1 pr-4 hover:bg-gray-50"
                        :style="{ paddingLeft: `${1 + depthOf(entry.name) * 1.25}rem` }">
                        <FontAwesomeIcon :icon="entry.is_directory ? 'fal fa-folder' : 'fal fa-file'" fixed-width :class="entry.is_directory ? 'text-amber-500' : 'text-gray-400'" />
                        <span class="min-w-0 flex-1 truncate" :title="entry.name">{{ baseNameOf(entry.name) }}</span>
                        <span v-if="!entry.is_directory" class="shrink-0 text-xs tabular-nums text-gray-400">{{ formatSize(entry.size) }}</span>
                    </li>
                    <li v-if="!sortedZipEntries.length" class="px-4 py-6 text-center text-gray-400">{{ ctrans("This archive is empty") }}</li>
                </ul>
                <p v-if="zipContents && zipContents.total > zipContents.entries.length" class="shrink-0 border-t border-gray-200 bg-gray-50 px-4 py-2 text-xs text-gray-500">
                    {{ ctrans("Showing the first :count of :total entries", { count: String(zipContents.entries.length), total: String(zipContents.total) }) }}
                </p>
            </div>
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
