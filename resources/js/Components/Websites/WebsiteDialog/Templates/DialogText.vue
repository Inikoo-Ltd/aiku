<script setup lang="ts">
import { computed, defineAsyncComponent, inject, ref, watch } from "vue"
import { hasText, useDialogTemplate } from "@/Components/Websites/WebsiteDialog/Templates/useDialogTemplate"
import { ctrans } from "@/Composables/useTrans"
import type { WebsiteDialogTemplateData } from "@/types/WebsiteDialog"

const EditorV2 = defineAsyncComponent(() => import("@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"))

type DialogTextFieldKey = "eyebrow" | "title" | "description" | "note"

const props = defineProps<{
    fieldKey: DialogTextFieldKey
    dialogData?: WebsiteDialogTemplateData
    isEditable?: boolean
}>()

const { fields, editable } = useDialogTemplate(props)
const websiteDialogData = inject<WebsiteDialogTemplateData | null>("websiteDialogData", null)

const inlineToolbar = [
    "fontSize", "bold", "italic", "underline", "fontFamily",
    "alignLeft", "alignCenter", "alignRight", "customLink",
    "undo", "redo", "highlight", "color", "clear",
]

const placeholder = computed(() => ({
    eyebrow: ctrans("Label above title"),
    title: ctrans("Title"),
    description: ctrans("Description"),
    note: ctrans("Small print"),
}[props.fieldKey]))

const editorKey = ref(0)
let lastInlineText: string | undefined

watch(() => fields.value[props.fieldKey]?.text, (text) => {
    if (text !== lastInlineText) {
        editorKey.value += 1
    }
})

const onInlineEdit = (text: string) => {
    if (!websiteDialogData) {
        return
    }

    lastInlineText = text
    websiteDialogData.fields[props.fieldKey] = { ...websiteDialogData.fields[props.fieldKey], text }
}
</script>

<template>
    <div v-if="isEditable" v-bind="editable(fieldKey)">
        <EditorV2
            :key="editorKey"
            :modelValue="fields[fieldKey]?.text ?? ''"
            :toggle="inlineToolbar"
            :placeholder="placeholder"
            @update:modelValue="onInlineEdit"
        />
    </div>
    <div v-else-if="hasText(fields[fieldKey]?.text)" v-html="fields[fieldKey]?.text" />
</template>
