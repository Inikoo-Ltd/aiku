<script setup lang="ts">
import Editor from '@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue'
import { EditorContent } from '@tiptap/vue-3'

const props = withDefaults(
  defineProps<{
    rows?: number
    class?: string
    keyData?: string | number
    uploadRoutes?: any
  }>(),
  {
    rows: 3
  }
)

const model = defineModel<string | null>()
</script>

<template>
  <div :class="props.class" >
    <Editor v-model="model" :uploadImageRoute="props.uploadRoutes">
      <template #editor-content="{ editor }">
        <div
          class="editor-wrapper border-2 border-gray-300 rounded-lg p-3 shadow-sm transition-all duration-200 focus-within:border-blue-400"
          :style="{ minHeight: `${props.rows * 24}px` }"
        >
          <EditorContent :editor="editor" class="editor-content" />
        </div>
      </template>
    </Editor>
  </div>
</template>

<style scoped lang="scss">
.editor-wrapper {
  display: flex;
  flex-direction: column;
  width: 100%;
  overflow-y: auto;
  box-sizing: border-box;
  max-height: 400px; // optional
}

.editor-content {
  flex: 1;
  width: 100%;
  display: block;
}

:deep(.ProseMirror) {
  outline: none;
  white-space: pre-wrap;
  word-break: break-word;
  overflow-wrap: break-word;
  width: 100%;
  height: auto;
  min-height: 100%;
  box-sizing: border-box;
  line-height: 1.5;
  font-size: 1rem;
}
</style>
