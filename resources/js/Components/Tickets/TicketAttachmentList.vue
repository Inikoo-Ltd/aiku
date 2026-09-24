<!--
 Author Louis Perez
 Created on 14-09-2026-16h-35m
 GitHub: https://github.com/louis-perez
 Copyright 2026
-->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { trans } from "laravel-vue-i18n"
import Image from "@/Common/Components/Image.vue"
import TicketAttachmentPreview, { isImageAttachment, isPdfAttachment, isPreviewableAttachment, isVideoAttachment, isWordAttachment, type TicketAttachment } from "@/Components/Tickets/TicketAttachmentPreview.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faChevronDown, faFile, faFileImage, faFilePdf, faFileWord, faFileExcel, faFileCsv, faFileVideo, faFileArchive } from "@fal"

library.add(faChevronDown, faFile, faFileImage, faFilePdf, faFileWord, faFileExcel, faFileCsv, faFileVideo, faFileArchive)

const props = defineProps<{
    files: TicketAttachment[]
    compact?: boolean
    previewBlocked?: boolean
}>()

const isExpanded = ref(true)
const loadedThumbnailUrls = ref<string[]>([])

const markThumbnailLoaded = (url: string) => {
    if (!loadedThumbnailUrls.value.includes(url)) loadedThumbnailUrls.value.push(url)
}
const previewIndex = ref<number | null>(null)

type AttachmentType = "image" | "pdf" | "word" | "excel" | "csv" | "video" | "other"

const typeLabels: Record<AttachmentType, string> = {
    image: trans("Images"),
    pdf: "PDF",
    word: "Word",
    excel: "Excel",
    csv: "CSV",
    video: trans("Videos"),
    other: trans("Other"),
}

const typeOf = (file: TicketAttachment): AttachmentType => {
    const extension = file.name.split(".").pop()?.toLowerCase() ?? ""
    if (isImageAttachment(file)) return "image"
    if (isPdfAttachment(file)) return "pdf"
    if (isWordAttachment(file)) return "word"
    if (extension === "csv") return "csv"
    if (["xls", "xlsx"].includes(extension)) return "excel"
    if (isVideoAttachment(file)) return "video"
    return "other"
}

const selectedType = ref<AttachmentType | "all">("all")

const typeOptions = computed(() =>
    (Object.keys(typeLabels) as AttachmentType[])
        .map((type) => ({ value: type, label: typeLabels[type], count: props.files.filter((file) => typeOf(file) === type).length }))
        .filter((option) => option.count > 0)
)

const activeType = computed(() => (selectedType.value === "all" || typeOptions.value.some((option) => option.value === selectedType.value) ? selectedType.value : "all"))

const filteredFiles = computed(() => (activeType.value === "all" ? props.files : props.files.filter((file) => typeOf(file) === activeType.value)))

const previewableFiles = computed(() => filteredFiles.value.filter(isPreviewableAttachment))

watch(activeType, () => {
    previewIndex.value = null
})

const fileIcons: Record<string, { icon: string; class: string }> = {
    pdf: { icon: "fal fa-file-pdf", class: "text-red-500" },
    docx: { icon: "fal fa-file-word", class: "text-blue-600" },
    xls: { icon: "fal fa-file-excel", class: "text-green-600" },
    xlsx: { icon: "fal fa-file-excel", class: "text-green-600" },
    csv: { icon: "fal fa-file-csv", class: "text-emerald-600" },
    zip: { icon: "fal fa-file-archive", class: "text-amber-600" },
    rar: { icon: "fal fa-file-archive", class: "text-amber-600" },
    "7z": { icon: "fal fa-file-archive", class: "text-amber-600" },
    mp4: { icon: "fal fa-file-video", class: "text-purple-600" },
    webm: { icon: "fal fa-file-video", class: "text-purple-600" },
    mov: { icon: "fal fa-file-video", class: "text-purple-600" },
    jpg: { icon: "fal fa-file-image", class: "text-sky-500" },
    jpeg: { icon: "fal fa-file-image", class: "text-sky-500" },
    png: { icon: "fal fa-file-image", class: "text-sky-500" },
    gif: { icon: "fal fa-file-image", class: "text-sky-500" },
    webp: { icon: "fal fa-file-image", class: "text-sky-500" },
    bmp: { icon: "fal fa-file-image", class: "text-sky-500" },
    svg: { icon: "fal fa-file-image", class: "text-sky-500" },
}

const iconFor = (file: TicketAttachment) => fileIcons[file.name.split(".").pop()?.toLowerCase() ?? ""] ?? { icon: "fal fa-file", class: "text-gray-400" }

const shortName = (name: string) => (name.length <= 26 ? name : `${name.slice(0, 14)} … ${name.slice(-8)}`)

const openFile = (file: TicketAttachment) => {
    if (props.previewBlocked) return
    const index = previewableFiles.value.indexOf(file)
    if (index >= 0) {
        previewIndex.value = index
        return
    }
    const link = document.createElement("a")
    link.href = file.url
    link.download = file.name
    link.click()
}
</script>

<template>
    <div v-if="files.length" class="rounded-lg border border-gray-200 bg-white" :class="compact ? 'p-3' : 'p-4'">
        <div class="flex items-center gap-2">
        <button type="button" class="flex items-center gap-2 text-left" @click="isExpanded = !isExpanded">
            <FontAwesomeIcon icon="fal fa-chevron-down" fixed-width class="text-gray-400 transition-transform" :class="[!isExpanded && '-rotate-90', compact && 'text-xs']" />
            <span class="font-semibold text-gray-800" :class="compact && 'text-sm'">{{ trans("Attachments") }}</span>
            <span class="rounded bg-gray-100 px-1.5 font-medium tabular-nums text-gray-600" :class="compact ? 'text-[11px]' : 'text-xs'">{{ filteredFiles.length === files.length ? files.length : `${filteredFiles.length}/${files.length}` }}</span>
        </button>
            <span v-if="previewBlocked" class="text-xs font-semibold text-amber-600">{{ trans("You can't preview these files because of your permissions.") }}</span>
            <select
                v-if="typeOptions.length > 1"
                v-model="selectedType"
                class="ml-auto cursor-pointer rounded-md border-gray-300 py-0.5 pl-2 pr-7 text-xs text-gray-600 focus:border-[--app-accent] focus:ring-[--app-accent]"
                :aria-label="trans('Filter attachments by type')">
                <option value="all">{{ trans("All types") }}</option>
                <option v-for="option in typeOptions" :key="option.value" :value="option.value">{{ option.label }} ({{ option.count }})</option>
            </select>
        </div>
        <div v-if="isExpanded" class="grid" :class="compact ? 'mt-2 grid-cols-[repeat(auto-fill,minmax(8rem,1fr))] gap-2' : 'mt-3 grid-cols-[repeat(auto-fill,minmax(10rem,1fr))] gap-3'">
            <button
                v-for="file in filteredFiles"
                :key="file.url"
                type="button"
                class="overflow-hidden rounded-lg border border-gray-200 text-left transition hover:border-[--app-accent-muted] hover:shadow-sm"
                :title="file.name"
                :disabled="previewBlocked"
                :class="previewBlocked && 'cursor-not-allowed opacity-70 hover:!border-gray-200 hover:!shadow-none'"
                @click="openFile(file)">
                <div class="relative flex items-center justify-center overflow-hidden bg-gray-50" :class="compact ? 'h-20' : 'h-24'">
                    <FontAwesomeIcon v-show="!loadedThumbnailUrls.includes(file.url)" :icon="iconFor(file).icon" :class="[iconFor(file).class, compact ? 'text-3xl' : 'text-4xl']" fixed-width />
                    <Image
                        v-if="file.thumbnail"
                        :src="file.thumbnail"
                        alt=""
                        image-cover
                        class="absolute inset-0 h-full w-full transition-opacity duration-200"
                        :class="loadedThumbnailUrls.includes(file.url) ? 'opacity-100' : 'opacity-0'"
                        @onLoadImage="markThumbnailLoaded(file.url)" />
                </div>
                <div class="px-2 py-1.5">
                    <p class="truncate text-xs font-semibold text-gray-800">{{ shortName(file.name) }}</p>
                    <p v-if="file.created_at" class="text-[11px] text-gray-500">{{ useFormatTime(file.created_at, { formatTime: "hm" }) }}</p>
                </div>
            </button>
        </div>
        <TicketAttachmentPreview v-model:index="previewIndex" :files="previewableFiles" />
    </div>
</template>
