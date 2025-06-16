<script setup lang="ts">
import Editor from '@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue'
import { EditorContent } from '@tiptap/vue-3'

const props = defineProps<{}>()
const model = defineModel<{ text: string | null }>()

</script>

<template>
  <div class="">
    <Editor v-model="model">
      <template #editor-content="{ editor }">
        <div class="editor-wrapper border-2 border-gray-300 rounded-lg p-3 shadow-sm focus-within:border-blue-400">
          <EditorContent :editor="editor" class="editor-content focus:outline-none" />
        </div>
      </template>
    </Editor>
  </div>
</template>

<style scoped lang="scss">
.editor-wrapper {
  transition: border-color 0.3s ease, box-shadow 0.3s ease;
}

.editor-content {
  min-height: 150px;
  font-size: 1rem;
  line-height: 1.5;
  display: grid;
}

:deep(.editor-content .ProseMirror ) {
  @apply h-full ;
}
</style>
