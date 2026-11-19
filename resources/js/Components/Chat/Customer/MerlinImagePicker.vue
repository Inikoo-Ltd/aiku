<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faImage, faTimes } from "@fortawesome/free-solid-svg-icons"
import { ctrans } from "@/Composables/useTrans"

const MAX_IMAGES = 3
const MAX_BYTES = 10 * 1024 * 1024
const ACCEPTED = ["image/jpeg", "image/png", "image/webp", "image/gif"]

const props = defineProps<{ modelValue: File[], disabled?: boolean }>()
const emit = defineEmits<{ (e: "update:modelValue", files: File[]): void }>()

const input = ref<HTMLInputElement>()
const error = ref("")
const previews = ref<string[]>([])

watch(() => props.modelValue, (files) => {
    previews.value.forEach((url) => URL.revokeObjectURL(url))
    previews.value = files.map((file) => URL.createObjectURL(file))
    if (!files.length) {
        error.value = ""
    }
}, { immediate: true })

onBeforeUnmount(() => previews.value.forEach((url) => URL.revokeObjectURL(url)))

const addFiles = (files: File[]) => {
    error.value = ""
    const images = files.filter((file) => ACCEPTED.includes(file.type))
    if (images.length < files.length) {
        error.value = ctrans("Only photos and screenshots (JPG, PNG, WEBP or GIF) can be sent.")
    }
    const small = images.filter((file) => file.size <= MAX_BYTES)
    if (small.length < images.length) {
        error.value = ctrans("Each image must be smaller than 10 MB.")
    }
    const next = [...props.modelValue, ...small]
    if (next.length > MAX_IMAGES) {
        error.value = ctrans("You can send up to :count images at a time.", { count: MAX_IMAGES })
    }
    emit("update:modelValue", next.slice(0, MAX_IMAGES))
}

const onPick = (event: Event) => {
    const target = event.target as HTMLInputElement
    addFiles(Array.from(target.files ?? []))
    target.value = ""
}

const onPaste = (event: ClipboardEvent) => {
    const files = Array.from(event.clipboardData?.files ?? []).filter((file) => file.type.startsWith("image/"))
    if (files.length) {
        event.preventDefault()
        addFiles(files)
    }
}

const remove = (index: number) => {
    emit("update:modelValue", props.modelValue.filter((_, position) => position !== index))
}

const onDrop = (event: DragEvent) => {
    const files = Array.from(event.dataTransfer?.files ?? [])
    if (files.length) {
        event.preventDefault()
        addFiles(files)
    }
}

defineExpose({ onPaste, onDrop })
</script>

<template>
    <div>
        <div class="flex flex-wrap items-center gap-2">
            <input ref="input" type="file" class="hidden" :accept="ACCEPTED.join(',')" multiple @change="onPick" />
            <button type="button" class="flex h-8 items-center justify-center gap-1.5 whitespace-nowrap rounded border px-2 text-xs text-gray-500 hover:bg-gray-50 hover:text-gray-700 disabled:opacity-50"
                :disabled="disabled || modelValue.length >= MAX_IMAGES"
                @click="input?.click()">
                <FontAwesomeIcon :icon="faImage" fixed-width aria-hidden="true" />
                {{ ctrans("Add image") }}
            </button>
            <div v-for="(preview, index) in previews" :key="preview" class="relative h-12 w-12 overflow-hidden rounded border bg-gray-50">
                <img :src="preview" :alt="modelValue[index]?.name" class="h-full w-full object-cover" />
                <button type="button" class="absolute right-0.5 top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-white/90 text-gray-600 hover:text-gray-900"
                    :aria-label="ctrans('Remove image')" @click="remove(index)">
                    <FontAwesomeIcon :icon="faTimes" class="text-[10px]" fixed-width aria-hidden="true" />
                </button>
            </div>
        </div>
        <p v-if="error" class="mt-1 text-xs text-red-500">{{ error }}</p>
    </div>
</template>
