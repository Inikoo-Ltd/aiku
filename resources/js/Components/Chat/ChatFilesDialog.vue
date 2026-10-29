<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from "vue"
import axios from "axios"
import Dialog from "primevue/dialog"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faChevronLeft, faChevronRight, faTh, faDownload, faExternalLinkAlt, faRedo, faFile, faFilePdf, faFileExcel, faFileWord, faFileAudio, faFileVideo, faFileImage } from "@fal"
import { ctrans } from "@/Composables/useTrans"

library.add(faChevronLeft, faChevronRight, faTh, faDownload, faExternalLinkAlt, faRedo, faFile, faFilePdf, faFileExcel, faFileWord, faFileAudio, faFileVideo, faFileImage)

interface PictureSources {
    webp?: string
    original?: string
}

interface ChatFile {
    id: number
    message_id: number
    from: "customer" | "agent"
    sender_name: string | null
    created_at: string | null
    is_image: boolean
    is_archived: boolean
    thumbnail: PictureSources | null
    full: PictureSources | null
    download_url: string
    open_url: string | null
    file_name: string | null
    file_size: number | null
    file_mime: string | null
}

type Filter = "all" | "pictures" | "files"

const props = defineProps<{
    sessionUlid: string
    isWhatsapp: boolean
}>()

const visible = defineModel<boolean>("visible", { default: false })

const items = ref<ChatFile[]>([])
const isLoading = ref(false)
const errorMessage = ref("")
const filter = ref<Filter>("all")
const openIndex = ref<number | null>(null)

const pictures = computed(() => items.value.filter((item) => item.is_image))
const files = computed(() => items.value.filter((item) => !item.is_image))

const filterOptions = computed(() => [
    { label: ctrans("All"), value: "all" as Filter, count: items.value.length },
    { label: ctrans("Pictures"), value: "pictures" as Filter, count: pictures.value.length },
    { label: ctrans("Files"), value: "files" as Filter, count: files.value.length },
])

const showPictures = computed(() => filter.value !== "files" && pictures.value.length > 0)
const showFiles = computed(() => filter.value !== "pictures" && files.value.length > 0)
const isEmpty = computed(() => !isLoading.value && !errorMessage.value && !showPictures.value && !showFiles.value)

const openPicture = computed(() => (openIndex.value === null ? null : pictures.value[openIndex.value] ?? null))

const load = async () => {
    isLoading.value = true
    errorMessage.value = ""

    try {
        const routeName = props.isWhatsapp ? "grp.api.chats.meta.sessions.files" : "grp.api.chats.sessions.files"
        const response = await axios.get(route(routeName, [props.sessionUlid]))
        items.value = response.data?.data ?? []
    } catch (error: any) {
        errorMessage.value = error?.response?.data?.message || ctrans("Could not load the pictures and files.")
    } finally {
        isLoading.value = false
    }
}

const step = (direction: number) => {
    if (openIndex.value === null || pictures.value.length === 0) {
        return
    }

    openIndex.value = (openIndex.value + direction + pictures.value.length) % pictures.value.length
}

const onKeydown = (event: KeyboardEvent) => {
    if (!visible.value || openIndex.value === null) {
        return
    }

    if (event.key === "ArrowLeft") {
        event.preventDefault()
        step(-1)
    } else if (event.key === "ArrowRight") {
        event.preventDefault()
        step(1)
    } else if (event.key === "Escape") {
        event.preventDefault()
        event.stopPropagation()
        openIndex.value = null
    }
}

watch(visible, (isVisible) => {
    if (isVisible) {
        filter.value = "all"
        openIndex.value = null
        items.value = []
        window.addEventListener("keydown", onKeydown, true)
        load()
    } else {
        window.removeEventListener("keydown", onKeydown, true)
    }
}, { immediate: true })

onBeforeUnmount(() => window.removeEventListener("keydown", onKeydown, true))

const sourceOf = (sources: PictureSources | null): string | undefined => sources?.webp ?? sources?.original

const whoLabel = (item: ChatFile): string => item.from === "customer" ? ctrans("Customer") : ctrans("Agent")

const whoDetail = (item: ChatFile): string => item.from === "agent" && item.sender_name
    ? `${ctrans("Agent")} (${item.sender_name})`
    : whoLabel(item)

const formatDate = (value: string | null): string => value ? new Date(value).toLocaleString() : ""

const formatSize = (bytes: number | null): string => {
    if (!bytes) {
        return ""
    }

    if (bytes < 1024) {
        return `${bytes} B`
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(0)} KB`
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

const fileIcon = (item: ChatFile): string => {
    const mime = item.file_mime ?? ""

    if (mime.includes("pdf")) {
        return "fal fa-file-pdf"
    }

    if (mime.includes("excel") || mime.includes("spreadsheet") || mime.includes("csv")) {
        return "fal fa-file-excel"
    }

    if (mime.includes("word") || mime.includes("document")) {
        return "fal fa-file-word"
    }

    if (mime.startsWith("audio/")) {
        return "fal fa-file-audio"
    }

    if (mime.startsWith("video/")) {
        return "fal fa-file-video"
    }

    if (mime.startsWith("image/")) {
        return "fal fa-file-image"
    }

    return "fal fa-file"
}

const pictureIndex = (item: ChatFile): number => pictures.value.findIndex((picture) => picture.id === item.id)
</script>

<template>
    <Dialog v-model:visible="visible" modal dismissableMask :draggable="false" :header="ctrans('Pictures and files')"
        :style="{ width: '56rem', maxWidth: '90vw' }" :pt="{ content: { class: 'p-4' } }">
        <template #header>
            <div class="flex items-baseline gap-2">
                <span class="text-sm font-semibold text-gray-600">{{ ctrans("Pictures and files") }}</span>
                <span v-if="!isLoading && !errorMessage" class="text-xs text-gray-500">
                    {{ ctrans(":pictures pictures, :files files", { pictures: String(pictures.length), files: String(files.length) }) }}
                </span>
            </div>
        </template>

        <div v-if="openPicture" class="flex flex-col gap-3">
            <div class="relative flex min-h-[50vh] items-center justify-center rounded-lg bg-gray-50">
                <img :src="sourceOf(openPicture.full) ?? undefined" :alt="openPicture.file_name ?? ''"
                    class="max-h-[65vh] max-w-full rounded-lg object-contain" />
                <template v-if="pictures.length > 1">
                    <button type="button" :aria-label="ctrans('Previous')"
                        class="absolute left-2 top-1/2 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full border border-gray-300 bg-white/90 text-gray-600 hover:bg-gray-100"
                        @click="step(-1)">
                        <FontAwesomeIcon icon="fal fa-chevron-left" fixed-width aria-hidden="true" />
                    </button>
                    <button type="button" :aria-label="ctrans('Next')"
                        class="absolute right-2 top-1/2 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full border border-gray-300 bg-white/90 text-gray-600 hover:bg-gray-100"
                        @click="step(1)">
                        <FontAwesomeIcon icon="fal fa-chevron-right" fixed-width aria-hidden="true" />
                    </button>
                </template>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="min-w-0">
                    <div class="truncate text-sm font-medium text-gray-700">{{ openPicture.file_name }}</div>
                    <div class="text-xs text-gray-500">
                        {{ whoDetail(openPicture) }} · {{ formatDate(openPicture.created_at) }}
                        <template v-if="formatSize(openPicture.file_size)"> · {{ formatSize(openPicture.file_size) }}</template>
                        · {{ openIndex! + 1 }} / {{ pictures.length }}
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Button type="tertiary" size="xs" :label="ctrans('Back to gallery')" @click="openIndex = null">
                        <template #icon>
                            <FontAwesomeIcon icon="fal fa-th" class="text-gray-500" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                    <a :href="openPicture.download_url" download>
                        <Button type="tertiary" size="xs" :label="ctrans('Download')">
                            <template #icon>
                                <FontAwesomeIcon icon="fal fa-download" class="text-gray-500" fixed-width aria-hidden="true" />
                            </template>
                        </Button>
                    </a>
                    <a v-if="openPicture.open_url" :href="openPicture.open_url" target="_blank" rel="noopener">
                        <Button type="tertiary" size="xs" :label="ctrans('Open in new tab')">
                            <template #icon>
                                <FontAwesomeIcon icon="fal fa-external-link-alt" class="text-gray-500" fixed-width aria-hidden="true" />
                            </template>
                        </Button>
                    </a>
                </div>
            </div>
        </div>

        <div v-else class="flex flex-col gap-4">
            <SegmentedToggle v-model="filter" :options="filterOptions" :aria-label="ctrans('Pictures and files')" />

            <div v-if="isLoading" class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                <div v-for="n in 8" :key="n" class="aspect-square animate-pulse rounded-lg bg-gray-100" />
            </div>

            <div v-else-if="errorMessage" class="flex items-center gap-3">
                <span class="text-xs text-red-600">{{ errorMessage }}</span>
                <Button type="tertiary" size="xs" :label="ctrans('Try again')" @click="load">
                    <template #icon>
                        <FontAwesomeIcon icon="fal fa-redo" class="text-gray-500" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </div>

            <div v-else-if="isEmpty" class="py-10 text-center text-sm text-gray-500">
                {{ ctrans("No pictures or files in this conversation yet.") }}
            </div>

            <template v-else>
                <div v-if="showPictures" class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                    <button v-for="item in pictures" :key="item.id" type="button"
                        class="group flex flex-col gap-1 text-left" @click="openIndex = pictureIndex(item)">
                        <span class="block aspect-square overflow-hidden rounded-lg border border-gray-200 bg-gray-50 group-hover:border-[--app-accent-muted]">
                            <img :src="sourceOf(item.thumbnail) ?? undefined" :alt="item.file_name ?? ''" loading="lazy"
                                class="h-full w-full object-cover" />
                        </span>
                        <span class="truncate text-xs text-gray-500">{{ whoLabel(item) }} · {{ formatDate(item.created_at) }}</span>
                    </button>
                </div>

                <div v-if="showFiles" class="divide-y divide-gray-100 rounded-lg border border-gray-200">
                    <div v-for="item in files" :key="item.id" class="flex items-center gap-3 px-3 py-2">
                        <FontAwesomeIcon :icon="fileIcon(item)" class="text-lg text-gray-400" fixed-width aria-hidden="true" />
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm text-gray-700">{{ item.file_name }}</div>
                            <div class="text-xs text-gray-500">
                                <template v-if="formatSize(item.file_size)">{{ formatSize(item.file_size) }} · </template>{{ whoLabel(item) }} · {{ formatDate(item.created_at) }}
                            </div>
                        </div>
                        <a :href="item.download_url" download>
                            <Button type="tertiary" size="xs" :label="ctrans('Download')">
                                <template #icon>
                                    <FontAwesomeIcon icon="fal fa-download" class="text-gray-500" fixed-width aria-hidden="true" />
                                </template>
                            </Button>
                        </a>
                    </div>
                </div>
            </template>
        </div>
    </Dialog>
</template>
