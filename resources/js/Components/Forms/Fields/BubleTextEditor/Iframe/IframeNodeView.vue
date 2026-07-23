<script setup lang="ts">
import { ref } from "vue"
import { NodeViewWrapper } from "@tiptap/vue-3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPen, faCheck, faTimes } from "@fal"

const props = defineProps<{
    node: any
    updateAttributes: (attrs: Record<string, any>) => void
    editor: any
}>()

const isEditing = ref(false)
const draftUrl = ref("")

const normalizeUrl = (url: string): string => {
    const trimmed = url.trim()
    const match = trimmed.match(/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([\w-]+)/)
    if (match) {
        return `https://www.youtube.com/embed/${match[1]}`
    }
    return trimmed
}

const openEditor = () => {
    draftUrl.value = props.node.attrs.src ?? ""
    isEditing.value = true
}

const saveUrl = () => {
    props.updateAttributes({ src: normalizeUrl(draftUrl.value) })
    isEditing.value = false
}

const cancelEditor = () => {
    isEditing.value = false
}
</script>

<template>
    <NodeViewWrapper class="relative group" data-iframe-wrapper>
        <iframe
            :src="node.attrs.src"
            :width="node.attrs.width"
            :height="node.attrs.height"
            :frameborder="node.attrs.frameborder"
            :allowfullscreen="node.attrs.allowfullscreen"
        />

        <button
            v-if="editor.isEditable && !isEditing"
            type="button"
            contenteditable="false"
            class="absolute top-2 right-2 whitespace-nowrap z-10 hidden group-hover:flex items-center justify-center h-8 xw-8 px-2 rounded-full bg-yellow-500 text-black hover:bg-yellow-700 cursor-pointer shadow"
            @click="openEditor"
        >
            {{ ctrans("Edit Iframe URL") }}
            <FontAwesomeIcon :icon="faPen" fixed-width class="ml-2" />
        </button>

        <div
            v-if="isEditing"
            contenteditable="false"
            class="absolute top-2 left-2 right-2 z-20 flex items-center gap-2 rounded-lg bg-white p-2 shadow-lg border border-gray-300"
        >
            <input
                v-model="draftUrl"
                type="text"
                placeholder="https://www.youtube.com/watch?v=..."
                class="flex-1 min-w-0 rounded border border-gray-300 px-2 py-1 text-sm focus:outline-none focus:border-[var(--theme-color-0)]"
                @keydown.enter.prevent="saveUrl"
                @keydown.esc.prevent="cancelEditor"
            />
            <button
                type="button"
                class="flex items-center justify-center h-7 w-7 rounded bg-green-500 text-white hover:bg-green-600"
                @click="saveUrl"
            >
                <FontAwesomeIcon :icon="faCheck" fixed-witdh class="h-3.5 w-3.5" />
            </button>
            <button
                type="button"
                class="flex items-center justify-center h-7 w-7 rounded bg-gray-200 text-gray-700 hover:bg-gray-300"
                @click="cancelEditor"
            >
                <FontAwesomeIcon :icon="faTimes" class="h-3.5 w-3.5" />
            </button>
        </div>
    </NodeViewWrapper>
</template>
