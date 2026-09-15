<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref, watch, onBeforeUnmount } from "vue"
import { marked } from "marked"
import Image from "@/Common/Components/Image.vue"
import TicketAttachmentPreview, { isPreviewableAttachment, type TicketAttachment } from "@/Components/Tickets/TicketAttachmentPreview.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { trans } from "laravel-vue-i18n"
import { useModalFocusTrap } from "@/Composables/useModalFocusTrap"
import { faPaperclip, faTimes, faChevronLeft, faChevronRight, faExternalLink, faImage } from "@fal"

library.add(faPaperclip, faTimes, faChevronLeft, faChevronRight, faExternalLink, faImage)

const props = defineProps<{
    text: string | null
    images?: Record<string, string>[]
    attachments?: TicketAttachment[]
}>()

const previewableFiles = computed(() => (props.attachments ?? []).filter(isPreviewableAttachment))

const previewFileIndex = ref<number | null>(null)

const escapeHtml = (text: string) => text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")

const html = computed(() => {
    const rendered = marked.parse(escapeHtml(props.text ?? ""), { breaks: true, gfm: true, async: false }) as string
    return rendered.replace(/<a /g, '<a target="_blank" rel="noopener" ')
})

const previewIndex = ref<number | null>(null)
const lightbox = ref<HTMLElement | null>(null)

useModalFocusTrap(computed(() => previewIndex.value !== null), lightbox)
const loadedImageUrls = ref<string[]>([])

const markImageLoaded = (url: string) => {
    if (!loadedImageUrls.value.includes(url)) loadedImageUrls.value.push(url)
}
const unavailableImageUrls = ref<string[]>([])

const markImageUnavailable = (url: string) => {
    if (!unavailableImageUrls.value.includes(url)) unavailableImageUrls.value.push(url)
}

const openPreview = (index: number) => {
    previewIndex.value = index
}

const closePreview = () => {
    previewIndex.value = null
}

const prevImage = () => {
    const total = props.images?.length ?? 0
    if (previewIndex.value === null || !total) return
    previewIndex.value = (previewIndex.value - 1 + total) % total
}

const nextImage = () => {
    const total = props.images?.length ?? 0
    if (previewIndex.value === null || !total) return
    previewIndex.value = (previewIndex.value + 1) % total
}

const onKeydown = (event: KeyboardEvent) => {
    if (event.key === "Escape") closePreview()
    else if (event.key === "ArrowLeft") prevImage()
    else if (event.key === "ArrowRight") nextImage()
}

watch(
    () => previewIndex.value !== null,
    (isOpen) => {
        if (isOpen) window.addEventListener("keydown", onKeydown)
        else window.removeEventListener("keydown", onKeydown)
    }
)

onBeforeUnmount(() => window.removeEventListener("keydown", onKeydown))
</script>

<template>
    <div>
        <div v-if="text" class="ticket-body text-sm break-words" v-html="html" />
        <div v-if="images?.length" class="mt-2 flex flex-wrap gap-2">
            <button v-for="(image, index) in images" :key="index" type="button" class="block cursor-zoom-in" @click="openPreview(index)">
                <span class="relative flex h-32 w-32 items-center justify-center overflow-hidden rounded border border-gray-200 bg-gray-50">
                    <FontAwesomeIcon v-show="!loadedImageUrls.includes(image.original)" icon="fal fa-image" class="text-3xl text-gray-300" />
                    <Image
                        :src="image"
                        alt=""
                        image-cover
                        class="absolute inset-0 h-full w-full transition-opacity duration-200 hover:opacity-90"
                        :class="loadedImageUrls.includes(image.original) ? 'opacity-100' : 'opacity-0'"
                        @onLoadImage="markImageLoaded(image.original)" />
                </span>
            </button>
        </div>
        <Teleport to="body">
            <div v-if="previewIndex !== null && images?.length" ref="lightbox" tabindex="-1" class="fixed inset-0 z-[9999] flex items-center justify-center overscroll-contain bg-black/80 outline-none" @click.self="closePreview">
                <div class="absolute inset-x-0 top-0 flex items-center justify-between gap-4 px-4 py-3 text-white">
                    <span class="truncate text-sm">{{ images[previewIndex].name }}</span>
                    <div class="flex shrink-0 items-center gap-4">
                        <a :href="images[previewIndex].original" target="_blank" rel="noopener" v-tooltip="trans('Open in new tab')" class="text-2xl text-white/80 hover:text-white">
                            <FontAwesomeIcon icon="fal fa-external-link" fixed-width />
                        </a>
                        <button type="button" class="text-3xl text-white/80 hover:text-white" @click="closePreview">
                            <FontAwesomeIcon icon="fal fa-times" fixed-width />
                        </button>
                    </div>
                </div>
                <button v-if="images.length > 1" type="button" class="absolute left-4 p-3 text-4xl text-white/80 hover:text-white" @click="prevImage">
                    <FontAwesomeIcon icon="fal fa-chevron-left" fixed-width />
                </button>
                <img v-if="!unavailableImageUrls.includes(images[previewIndex].original)" :src="images[previewIndex].original" alt="" class="max-h-[90vh] max-w-[85vw] rounded object-contain" @error="markImageUnavailable(images[previewIndex].original)" />
                <div v-else class="flex h-[60vh] w-[85vw] max-w-3xl items-center justify-center rounded bg-white text-sm text-gray-500">{{ trans("Preview for this file is unavailable") }}</div>
                <button v-if="images.length > 1" type="button" class="absolute right-4 p-3 text-4xl text-white/80 hover:text-white" @click="nextImage">
                    <FontAwesomeIcon icon="fal fa-chevron-right" fixed-width />
                </button>
                <div v-if="images.length > 1" class="absolute bottom-4 text-sm text-white/80">{{ previewIndex + 1 }} / {{ images.length }}</div>
            </div>
        </Teleport>
        <ul v-if="attachments?.length" class="mt-2 space-y-1 text-sm">
            <li v-for="file in attachments" :key="file.url">
                <button v-if="isPreviewableAttachment(file)" type="button" class="text-left text-indigo-600 hover:underline break-all" @click="previewFileIndex = previewableFiles.indexOf(file)"><FontAwesomeIcon icon="fal fa-paperclip" class="mr-1" />{{ file.name }}</button>
                <a v-else :href="file.url" target="_blank" rel="noopener" class="text-indigo-600 hover:underline break-all"><FontAwesomeIcon icon="fal fa-paperclip" class="mr-1" />{{ file.name }}</a>
            </li>
        </ul>
        <TicketAttachmentPreview v-model:index="previewFileIndex" :files="previewableFiles" />
    </div>
</template>

<style scoped>
.ticket-body :deep(p) { margin: 0 0 0.75em; }
.ticket-body :deep(p:last-child) { margin-bottom: 0; }
.ticket-body :deep(a) { color: rgb(79 70 229); text-decoration: underline; word-break: break-all; }
.ticket-body :deep(ul) { list-style: disc; padding-left: 1.5em; margin: 0 0 0.75em; }
.ticket-body :deep(ol) { list-style: decimal; padding-left: 1.5em; margin: 0 0 0.75em; }
.ticket-body :deep(h1), .ticket-body :deep(h2), .ticket-body :deep(h3) { font-weight: 600; margin: 0.5em 0; }
.ticket-body :deep(blockquote) { border-left: 3px solid rgb(209 213 219); padding-left: 0.75em; color: rgb(75 85 99); margin: 0 0 0.75em; }
.ticket-body :deep(pre) { background: rgb(243 244 246); padding: 0.5em; border-radius: 0.25em; overflow-x: auto; margin: 0 0 0.75em; }
.ticket-body :deep(code) { font-family: ui-monospace, monospace; font-size: 0.9em; }
.ticket-body :deep(table) { border-collapse: collapse; margin: 0 0 0.75em; }
.ticket-body :deep(td), .ticket-body :deep(th) { border: 1px solid rgb(209 213 219); padding: 0.25em 0.5em; }
</style>
