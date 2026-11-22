<script setup lang="ts">
import { computed, ref } from "vue"
import InputText from "primevue/inputtext"
import { EditorContent } from "@tiptap/vue-3"
import { get } from "lodash-es"
import Editor from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faLanguage } from "@fal"
library.add(faLanguage)

const props = defineProps<{
    form: Record<string, any>
    fieldName: string
    fieldData: {
        languages: Record<string, string>
        html?: boolean
    }
}>()

const codes = computed(() => Object.keys(props.fieldData.languages))
const activeCode = ref(codes.value[0])
const languageOptions = computed(() => codes.value.map(code => ({ label: props.fieldData.languages[code], value: code })))
</script>

<template>
    <div class="space-y-2">
        <SegmentedToggle v-if="codes.length > 1" v-model="activeCode" :options="languageOptions" soft />
        <InputText v-if="!fieldData.html" v-model="form[fieldName][activeCode]" :lang="activeCode" class="w-full" />
        <Editor v-else :key="activeCode" v-model="form[fieldName][activeCode]" :toggle="['bold', 'italic', 'underline', 'bulletList', 'orderedList', 'customLink', 'undo', 'redo', 'clear']">
            <template #editor-content="{ editor }">
                <div :lang="activeCode" class="rounded-lg border border-gray-300 p-3 focus-within:border-[var(--app-accent)]">
                    <EditorContent :editor="editor" class="min-h-[120px] focus:outline-none" />
                </div>
            </template>
        </Editor>
        <p v-if="get(form, ['errors', fieldName])" class="text-xs text-red-600">{{ form.errors[fieldName] }}</p>
    </div>
</template>
