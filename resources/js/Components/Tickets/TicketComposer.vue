<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, nextTick, ref, watch } from "vue"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPaperclip, faTimes, faFilePdf, faFileWord, faFileExcel, faFileCsv, faFileVideo, faFileZipper } from "@fortawesome/free-solid-svg-icons"

const props = defineProps<{
    body: string
    images: File[]
    placeholder?: string
    rows?: number
    mentionable?: { username: string; name: string | null; suggested?: boolean; is_customer?: boolean }[]
}>()

const emit = defineEmits<{
    (e: "update:body", value: string): void
    (e: "update:images", value: File[]): void
}>()

const MAX_IMAGES = 5
const fileInput = ref<HTMLInputElement | null>(null)
const attachmentIcons = {
    pdf: { icon: faFilePdf, class: "text-red-600" },
    docx: { icon: faFileWord, class: "text-blue-600" },
    xls: { icon: faFileExcel, class: "text-green-600" },
    xlsx: { icon: faFileExcel, class: "text-green-600" },
    csv: { icon: faFileCsv, class: "text-emerald-600" },
    zip: { icon: faFileZipper, class: "text-amber-600" },
    rar: { icon: faFileZipper, class: "text-amber-600" },
    "7z": { icon: faFileZipper, class: "text-amber-600" },
    mp4: { icon: faFileVideo, class: "text-purple-600" },
    webm: { icon: faFileVideo, class: "text-purple-600" },
    mov: { icon: faFileVideo, class: "text-purple-600" },
}

type AttachmentExtension = keyof typeof attachmentIcons

const previews = ref<{ url: string; name: string; attachment: (typeof attachmentIcons)[AttachmentExtension] | null }[]>([])
const isDragging = ref(false)

const attachmentExtensionOf = (file: File): AttachmentExtension | null => {
    const extension = file.name.split(".").pop()?.toLowerCase() ?? ""
    return extension in attachmentIcons ? (extension as AttachmentExtension) : null
}

const isAcceptedFile = (file: File) => file.type.startsWith("image/") || attachmentExtensionOf(file) !== null

watch(
    () => props.images,
    (images) => {
        previews.value.forEach((preview) => URL.revokeObjectURL(preview.url))
        previews.value = images.map((file) => {
            const extension = attachmentExtensionOf(file)
            return { url: URL.createObjectURL(file), name: file.name, attachment: extension ? attachmentIcons[extension] : null }
        })
    },
    { immediate: true }
)

const addFiles = (files: Iterable<File>) => {
    const accepted = Array.from(files).filter(isAcceptedFile)
    if (!accepted.length) return
    emit("update:images", [...props.images, ...accepted].slice(0, MAX_IMAGES))
}

const removeImage = (index: number) => emit("update:images", props.images.filter((_, i) => i !== index))

const clipboardFiles = (clipboard: DataTransfer | null): File[] => {
    if (clipboard?.types.includes("text/html") && clipboard.types.includes("text/plain")) return []
    const files = Array.from(clipboard?.files ?? [])
    if (files.length) return files.filter(isAcceptedFile)
    return Array.from(clipboard?.items ?? [])
        .filter((item) => item.kind === "file")
        .map((item) => item.getAsFile())
        .filter((file): file is File => file !== null && isAcceptedFile(file))
}

const onPaste = (event: ClipboardEvent) => {
    const files = clipboardFiles(event.clipboardData)
    if (files.length) {
        event.preventDefault()
        addFiles(files)
    }
}

const onDrop = (event: DragEvent) => {
    isDragging.value = false
    addFiles(event.dataTransfer?.files ?? [])
}

const textarea = ref<HTMLTextAreaElement | null>(null)
const mentionQuery = ref<string | null>(null)
const mentionIndex = ref(0)

const mentionSuggestions = computed(() => {
    if (mentionQuery.value === null || !props.mentionable?.length) return []
    const query = mentionQuery.value.toLowerCase()
    const matchesQuery = (user: { username: string; name: string | null }) => user.username.toLowerCase().startsWith(query) || (user.name ?? "").toLowerCase().split(" ").some((part) => part.startsWith(query))
    const candidates = query === "" ? props.mentionable.filter((user) => user.suggested ?? true) : props.mentionable.filter(matchesQuery)
    return [...candidates].sort((first, second) => Number(second.suggested ?? false) - Number(first.suggested ?? false)).slice(0, 8)
})

const detectMention = () => {
    const element = textarea.value
    if (!element) return
    const match = element.value.slice(0, element.selectionStart).match(/(?:^|[^\p{L}\p{N}._-])@([\p{L}\p{N}._-]*)$/u)
    mentionQuery.value = match ? match[1] : null
    mentionIndex.value = 0
}

const onInput = (event: Event) => {
    emit("update:body", (event.target as HTMLTextAreaElement).value)
    detectMention()
}

const insertMention = (username: string) => {
    const element = textarea.value
    if (!element || mentionQuery.value === null) return
    const caret = element.selectionStart
    const start = caret - mentionQuery.value.length
    const value = element.value.slice(0, start) + username + " " + element.value.slice(caret)
    emit("update:body", value)
    mentionQuery.value = null
    nextTick(() => {
        element.focus()
        element.selectionStart = element.selectionEnd = start + username.length + 1
    })
}

const onKeydown = (event: KeyboardEvent) => {
    if (!mentionSuggestions.value.length) return
    if (event.key === "ArrowDown" || event.key === "ArrowUp") {
        event.preventDefault()
        const count = mentionSuggestions.value.length
        mentionIndex.value = (mentionIndex.value + (event.key === "ArrowDown" ? 1 : count - 1)) % count
    } else if (event.key === "Enter" || event.key === "Tab") {
        event.preventDefault()
        insertMention(mentionSuggestions.value[mentionIndex.value].username)
    } else if (event.key === "Escape") {
        mentionQuery.value = null
    }
}

const appendMention = (username: string) => {
    const mention = `@${username} `
    const separator = !props.body || /\s$/.test(props.body) ? "" : " "
    const value = props.body + separator + mention
    emit("update:body", value)
    mentionQuery.value = null
    nextTick(() => {
        const element = textarea.value
        if (!element) return
        element.focus()
        element.selectionStart = element.selectionEnd = value.length
        element.scrollIntoView({ block: "center", behavior: "smooth" })
    })
}

defineExpose({ appendMention })

const onPick = (event: Event) => {
    addFiles((event.target as HTMLInputElement).files ?? [])
    if (fileInput.value) fileInput.value.value = ""
}
</script>

<template>
    <div
        class="rounded-md border bg-white"
        :class="isDragging ? 'border-[--app-accent] ring-2 ring-[--app-accent-muted]' : 'border-gray-300'"
        @dragover.prevent="isDragging = true"
        @dragleave="isDragging = false"
        @drop.prevent="onDrop"
    >
        <div class="relative">
            <textarea
                ref="textarea"
                :value="body"
                :rows="rows ?? 5"
                class="w-full border-0 rounded-t-md text-sm focus:ring-0 resize-y"
                :placeholder="placeholder ?? trans('Describe it. Paste a screenshot or drop images here, links are fine.')"
                @input="onInput"
                @keydown="onKeydown"
                @click="detectMention"
                @blur="mentionQuery = null"
                @paste="onPaste"
            />
            <ul v-if="mentionSuggestions.length" class="absolute left-2 top-full z-20 -mt-2 w-64 rounded-md border border-gray-200 bg-white py-1 text-sm shadow-lg">
                <li
                    v-for="(user, index) in mentionSuggestions"
                    :key="user.username"
                    class="flex cursor-pointer gap-2 px-3 py-1.5"
                    :class="index === mentionIndex ? 'bg-[--app-accent-soft] text-[--app-accent-strong]' : 'text-gray-700'"
                    @mousedown.prevent="insertMention(user.username)"
                    @mouseenter="mentionIndex = index"
                >
                    <span class="font-medium">@{{ user.username }}</span>
                    <span v-if="user.name" class="truncate text-gray-500">{{ user.name }}</span>
                    <span v-if="user.is_customer" class="ml-auto shrink-0 rounded bg-blue-50 px-1.5 text-[10px] font-medium text-blue-700">{{ trans("Customer") }}</span>
                </li>
            </ul>
        </div>
        <div class="flex items-center gap-2 px-2 py-1.5 border-t border-gray-200">
            <button type="button" class="text-gray-500 hover:text-gray-800 text-sm flex items-center gap-1.5" :title="trans('Attach images, videos, PDF, Word, Excel or CSV')" @click="fileInput?.click()">
                <FontAwesomeIcon :icon="faPaperclip" fixed-width /> {{ trans("Attach") }}
            </button>
            <span class="text-xs text-gray-400">{{ trans("or paste / drop") }}</span>
            <input ref="fileInput" type="file" accept="image/*,.mp4,.webm,.mov,.pdf,.docx,.xls,.xlsx,.csv,.zip,.rar,.7z" multiple class="hidden" @change="onPick" />
            <div v-if="previews.length" class="ml-auto flex gap-1.5">
                <div v-for="(preview, index) in previews" :key="preview.url" class="relative">
                    <div v-if="preview.attachment" class="h-12 w-12 rounded border border-gray-200 bg-gray-50 flex flex-col items-center justify-center" :class="preview.attachment.class" :title="preview.name">
                        <FontAwesomeIcon :icon="preview.attachment.icon" class="text-lg" fixed-width />
                        <span class="w-full truncate px-0.5 text-center text-[9px] text-gray-500">{{ preview.name }}</span>
                    </div>
                    <img v-else :src="preview.url" alt="" class="h-12 w-12 rounded object-cover border border-gray-200" />
                    <button type="button" class="absolute -top-1.5 -right-1.5 h-4 w-4 rounded-full bg-gray-700 text-white text-[10px] flex items-center justify-center" @click="removeImage(index)">
                        <FontAwesomeIcon :icon="faTimes" fixed-width />
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
