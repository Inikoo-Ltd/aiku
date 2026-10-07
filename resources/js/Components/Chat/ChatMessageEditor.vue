<script setup lang="ts">
import { watch, onBeforeUnmount } from "vue"
import { useEditor, EditorContent } from "@tiptap/vue-3"
import StarterKit from "@tiptap/starter-kit"
import Underline from "@tiptap/extension-underline"
import Placeholder from "@tiptap/extension-placeholder"
import { markupToEditorHtml, editorDocToMarkup } from "@/Composables/useWhatsappMarkup"

const props = withDefaults(defineProps<{
    modelValue?: string | null
    placeholder?: string
    disabled?: boolean
    enterSends?: boolean
    allowUnderline?: boolean
}>(), {
    modelValue: "",
    placeholder: "",
})

const emit = defineEmits<{
    (e: "update:modelValue", value: string): void
    (e: "submit"): void
    (e: "paste", event: ClipboardEvent): void
    (e: "blur"): void
}>()

let lastEmitted = props.modelValue ?? ""

const editor = useEditor({
    content: markupToEditorHtml(props.modelValue),
    editable: !props.disabled,
    extensions: [
        StarterKit.configure({ heading: false, blockquote: false, codeBlock: false, horizontalRule: false }),
        Underline,
        Placeholder.configure({ placeholder: () => props.placeholder }),
    ],
    editorProps: {
        handleKeyDown: (_view, event) => {
            const modifier = event.metaKey || event.ctrlKey

            if (event.key === "Enter" && (modifier || (props.enterSends && !event.shiftKey))) {
                event.preventDefault()
                emit("submit")
                return true
            }

            return modifier && event.key.toLowerCase() === "u" && !props.allowUnderline
        },
        handlePaste: (_view, event) => {
            emit("paste", event)
            return event.defaultPrevented
        },
    },
    onUpdate: ({ editor }) => {
        lastEmitted = editorDocToMarkup(editor.getJSON() as any)
        emit("update:modelValue", lastEmitted)
    },
    onBlur: () => emit("blur"),
})

watch(() => props.modelValue, (value) => {
    if (!editor.value || (value ?? "") === lastEmitted) return
    lastEmitted = value ?? ""
    editor.value.commands.setContent(markupToEditorHtml(value), false)
})

watch(() => props.disabled, (disabled) => editor.value?.setEditable(!disabled))

onBeforeUnmount(() => editor.value?.destroy())

defineExpose({
    editor,
    focus: () => editor.value?.commands.focus(),
    insertText: (text: string) => editor.value?.chain().focus().insertContent(text).run(),
})
</script>

<template>
    <EditorContent :editor="editor"
        class="chat-message-editor text-sm leading-5 [&_.ProseMirror]:outline-none [&_.ProseMirror]:overflow-y-auto [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5 [&_code]:rounded [&_code]:bg-black/10 [&_code]:px-1 [&_code]:font-mono"
        :class="disabled ? 'bg-gray-50 text-gray-400 cursor-not-allowed' : ''" />
</template>

<style scoped>
.chat-message-editor :deep(p.is-editor-empty:first-child::before) {
    content: attr(data-placeholder);
    float: left;
    height: 0;
    pointer-events: none;
    color: #9ca3af;
}
</style>
