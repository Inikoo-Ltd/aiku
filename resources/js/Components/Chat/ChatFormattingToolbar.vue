<script setup lang="ts">
import { computed } from "vue"
import type { Editor } from "@tiptap/vue-3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faBold, faItalic, faUnderline, faStrikethrough, faListUl, faListOl } from "@fortawesome/free-solid-svg-icons"
import { ctrans } from "@/Composables/useTrans"

const props = defineProps<{
    editor?: Editor | null
    allowUnderline?: boolean
}>()

const buttons = computed(() => [
    { name: "bold", icon: faBold, label: ctrans("Bold") + " (Ctrl+B)", run: (e: Editor) => e.chain().focus().toggleBold().run() },
    { name: "italic", icon: faItalic, label: ctrans("Italic") + " (Ctrl+I)", run: (e: Editor) => e.chain().focus().toggleItalic().run() },
    ...(props.allowUnderline
        ? [{ name: "underline", icon: faUnderline, label: ctrans("Underline") + " (Ctrl+U)", run: (e: Editor) => e.chain().focus().toggleUnderline().run() }]
        : []),
    { name: "strike", icon: faStrikethrough, label: ctrans("Strikethrough"), run: (e: Editor) => e.chain().focus().toggleStrike().run() },
    { name: "bulletList", icon: faListUl, label: ctrans("Bullet list"), run: (e: Editor) => e.chain().focus().toggleBulletList().run() },
    { name: "orderedList", icon: faListOl, label: ctrans("Numbered list"), run: (e: Editor) => e.chain().focus().toggleOrderedList().run() },
])
</script>

<template>
    <div class="flex items-center gap-0.5">
        <button v-for="button in buttons" :key="button.name" type="button" @mousedown.prevent
            @click="editor && button.run(editor)"
            class="w-7 h-7 flex items-center justify-center rounded-lg hover:bg-gray-100"
            :class="editor?.isActive(button.name) ? 'bg-gray-200 text-gray-900' : 'text-gray-500'"
            v-tooltip="button.label" :aria-label="button.label">
            <FontAwesomeIcon :icon="button.icon" class="text-xs" fixed-width />
        </button>
    </div>
</template>
