<script setup lang="ts">
import Editor from '@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue'
import { EditorContent } from '@tiptap/vue-3'
import { trans } from 'laravel-vue-i18n'
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
        <div class="editor-wrapper h-full border-2 border-gray-300 rounded-lg p-3 shadow-sm focus-within:border-[var(--theme-color-0)]">
          <EditorContent :editor="editor" class="editor-content focus:outline-hidden" />
        </div>
      </template>
    </Editor>
    
      <!-- Counter: Letters and Words -->
      <div v-if="props.options?.counter"
          class="grid grid-flow-col text-xs italic text-gray-500 mt-2 space-x-12 justify-start tabular-nums">
          <p class="">{{ trans('Characters') }}: {{ form[fieldName]?.length ?? 0 }}</p>
          <p class="">
              {{ trans('Words') }}: {{ form[fieldName]?.trim().split(/\s+/).filter(Boolean).length ?? 0 }}
          </p>
      </div>
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
