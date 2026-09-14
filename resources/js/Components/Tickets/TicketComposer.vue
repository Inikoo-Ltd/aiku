<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref, watch } from "vue"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPaperclip, faTimes, faFilePdf } from "@fortawesome/free-solid-svg-icons"

const props = defineProps<{
    body: string
    images: File[]
    placeholder?: string
    rows?: number
}>()

const emit = defineEmits<{
    (e: "update:body", value: string): void
    (e: "update:images", value: File[]): void
}>()

const MAX_IMAGES = 5
const fileInput = ref<HTMLInputElement | null>(null)
const previews = ref<{ url: string; name: string; isPdf: boolean }[]>([])
const isDragging = ref(false)

const isAcceptedFile = (file: File) => file.type.startsWith("image/") || file.type === "application/pdf"

watch(
    () => props.images,
    (images) => {
        previews.value.forEach((preview) => URL.revokeObjectURL(preview.url))
        previews.value = images.map((file) => ({ url: URL.createObjectURL(file), name: file.name, isPdf: file.type === "application/pdf" }))
    },
    { immediate: true }
)

const addFiles = (files: Iterable<File>) => {
    const accepted = Array.from(files).filter(isAcceptedFile)
    if (!accepted.length) return
    emit("update:images", [...props.images, ...accepted].slice(0, MAX_IMAGES))
}

const removeImage = (index: number) => emit("update:images", props.images.filter((_, i) => i !== index))

const onPaste = (event: ClipboardEvent) => {
    const files = Array.from(event.clipboardData?.files ?? []).filter((file) => file.type.startsWith("image/"))
    if (files.length) {
        event.preventDefault()
        addFiles(files)
    }
}

const onDrop = (event: DragEvent) => {
    isDragging.value = false
    addFiles(event.dataTransfer?.files ?? [])
}

const onPick = (event: Event) => {
    addFiles((event.target as HTMLInputElement).files ?? [])
    if (fileInput.value) fileInput.value.value = ""
}
</script>

<template>
    <div
        class="rounded-md border bg-white"
        :class="isDragging ? 'border-indigo-400 ring-2 ring-indigo-100' : 'border-gray-300'"
        @dragover.prevent="isDragging = true"
        @dragleave="isDragging = false"
        @drop.prevent="onDrop"
    >
        <textarea
            :value="body"
            :rows="rows ?? 5"
            class="w-full border-0 rounded-t-md text-sm focus:ring-0 resize-y"
            :placeholder="placeholder ?? trans('Describe it. Paste a screenshot or drop images here, links are fine.')"
            @input="emit('update:body', ($event.target as HTMLTextAreaElement).value)"
            @paste="onPaste"
        />
        <div class="flex items-center gap-2 px-2 py-1.5 border-t border-gray-200">
            <button type="button" class="text-gray-500 hover:text-gray-800 text-sm flex items-center gap-1.5" :title="trans('Attach images or PDF')" @click="fileInput?.click()">
                <FontAwesomeIcon :icon="faPaperclip" /> {{ trans("Attach") }}
            </button>
            <span class="text-xs text-gray-400">{{ trans("or paste / drop") }}</span>
            <input ref="fileInput" type="file" accept="image/*,application/pdf" multiple class="hidden" @change="onPick" />
            <div v-if="previews.length" class="ml-auto flex gap-1.5">
                <div v-for="(preview, index) in previews" :key="preview.url" class="relative">
                    <div v-if="preview.isPdf" class="h-12 w-12 rounded border border-gray-200 bg-gray-50 flex flex-col items-center justify-center text-red-600" :title="preview.name">
                        <FontAwesomeIcon :icon="faFilePdf" class="text-lg" />
                        <span class="w-full truncate px-0.5 text-center text-[9px] text-gray-500">{{ preview.name }}</span>
                    </div>
                    <img v-else :src="preview.url" alt="" class="h-12 w-12 rounded object-cover border border-gray-200" />
                    <button type="button" class="absolute -top-1.5 -right-1.5 h-4 w-4 rounded-full bg-gray-700 text-white text-[10px] flex items-center justify-center" @click="removeImage(index)">
                        <FontAwesomeIcon :icon="faTimes" />
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
