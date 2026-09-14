<!--
 Author Louis Perez
 Created on 14-09-2026-14h-23m
 GitHub: https://github.com/louis-perez
 Copyright 2026
-->

<script lang="ts">
export type TicketAttachment = { name: string; url: string; size?: number; mime?: string | null }

export const isPdfAttachment = (file: TicketAttachment) => file.mime === "application/pdf" || file.name.toLowerCase().endsWith(".pdf")
</script>

<script setup lang="ts">
import { computed, ref, watch, onBeforeUnmount } from "vue"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTimes, faChevronLeft, faChevronRight, faExternalLink } from "@fal"

library.add(faTimes, faChevronLeft, faChevronRight, faExternalLink)

const props = defineProps<{
    files: TicketAttachment[]
}>()

const index = defineModel<number | null>("index", { default: null })

const currentFile = computed(() => (index.value === null ? null : props.files[index.value] ?? null))

const isCheckingFile = ref(false)
const isFileMissing = ref(false)

const checkFileAvailability = async (url: string) => {
    isCheckingFile.value = true
    isFileMissing.value = false
    try {
        const response = await fetch(url, { method: "HEAD" })
        if (currentFile.value?.url === url) isFileMissing.value = !response.ok
    } catch {
        isFileMissing.value = false
    } finally {
        if (currentFile.value?.url === url) isCheckingFile.value = false
    }
}

watch(
    () => currentFile.value?.url,
    (url) => {
        if (url) checkFileAvailability(url)
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
            <iframe v-if="!isCheckingFile && !isFileMissing" :key="currentFile.url" :src="currentFile.url" :title="currentFile.name" class="h-[85vh] w-full max-w-5xl rounded bg-white" />
            <div v-else class="flex h-[85vh] w-full max-w-5xl items-center justify-center rounded bg-white text-sm text-gray-500">
                <span v-if="isFileMissing">{{ trans("Preview for this file is unavailable") }}</span>
            </div>
            <button v-if="files.length > 1" type="button" class="absolute right-2 top-1/2 -translate-y-1/2 p-3 text-4xl text-white/80 hover:text-white" @click="next">
                <FontAwesomeIcon icon="fal fa-chevron-right" fixed-width />
            </button>
        </div>
    </Teleport>
</template>
