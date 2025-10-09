<script setup lang="ts">
import Editor from '@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue'
import { EditorContent } from '@tiptap/vue-3'
import { get } from 'lodash-es'

const props = defineProps<{
    form: any
    fieldName: string
    options?: any
    fieldData?: {
        type: string
        placeholder: string
        readonly?: boolean
        copyButton: boolean
        maxLength?: number
    }
}>()

</script>

<template>
  <div class="">
    <Editor v-model="form[fieldName]">
      <template #editor-content="{ editor }">
        <div class="editor-wrapper h-full border-2 border-gray-300 rounded-lg p-3 shadow-sm focus-within:border-[var(--grp-color-primary)]">
          <EditorContent :editor="editor" class="editor-content focus:outline-none" />
        </div>
      </template>
    </Editor>
  </div>
    <p v-if="get(form, ['errors', `${fieldName}`])" class="mt-2 text-sm text-red-600" :id="`${fieldName}-error`">
        {{ form.errors[fieldName] }}
    </p>
</template>

<style scoped>
:deep(.editor-class) {
  min-height: 150px;
}
.editor-wrapper {
  transition: border-color 0.3s ease, box-shadow 0.3s ease;
}

.editor-content {
  min-height: 150px;
  font-size: 1rem;
  line-height: 1.5;
}
</style>
