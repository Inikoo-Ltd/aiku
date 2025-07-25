<script setup lang="ts">
import Editor from '@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue'
import { EditorContent } from '@tiptap/vue-3'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import { ref, computed } from 'vue'
import { faLanguage } from '@fal'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

const props = defineProps<{
  form: Record<string, any>
  fieldName: string
  fieldData: {
    type: string
    placeholder: string
    readonly?: boolean
    copyButton: boolean
    maxLength?: number
  }
}>()

const translationData = computed(() => props.form[props.fieldName] || {})

const defaultLang = computed(() =>
  Object.entries(translationData.value).find(([_lang, value]: any) => value?.default)
)

const otherLangs = computed(() =>
  Object.entries(translationData.value).filter(([_lang, value]: any) => !value?.default)
)

const showModal = ref(false)
</script>

<template>
  <div class="space-y-4">
    <!-- ✅ Default Language Editor -->
    <div class="rounded-xl bg-white shadow-md border border-gray-200 transition-all">
      <!-- Label -->
      <div class="px-4 pt-3 pb-1 border-b border-gray-100 bg-gray-50 rounded-t-xl">
        <span class="block text-sm font-semibold text-gray-700 capitalize tracking-wide">
          {{ defaultLang?.[0] }}
        </span>
      </div>

      <!-- Editor -->
      <div class="p-1">
        <Editor v-model="translationData[defaultLang?.[0]]['value']">
          <template #editor-content="{ editor }">
            <div
              class="editor-wrapper border border-gray-300 rounded-md bg-white p-3 focus-within:border-blue-400 transition-all">
              <EditorContent :editor="editor" class="editor-content focus:outline-none" />
            </div>
          </template>
        </Editor>
      </div>
    </div>

    <!-- ✅ Modal Trigger Button -->
    <button
      class="text-sm text-blue-600 underline hover:text-blue-800 transition"
      @click="showModal = true"
    >
      <FontAwesomeIcon :icon="faLanguage" /> Edit other languages
    </button>

    <!-- ✅ Modal for Other Languages -->
    <Dialog v-model:visible="showModal" modal header="Translations" :style="{ width: '90vw', maxWidth: '900px' }">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div v-for="[lang, data] in otherLangs" :key="lang"
          class="rounded-xl bg-white shadow-md border border-gray-200 transition-all hover:shadow-lg">
          <!-- Label -->
          <div class="px-4 pt-3 pb-1 border-b border-gray-100 bg-gray-50 rounded-t-xl">
            <span class="block text-sm font-semibold text-gray-700 capitalize tracking-wide">
              {{ lang }}
            </span>
          </div>

          <!-- Editor -->
          <div class="p-1">
            <Editor v-model="data.value">
              <template #editor-content="{ editor }">
                <div
                  class="editor-wrapper border border-gray-300 rounded-md bg-white p-3 focus-within:border-blue-400 transition-all">
                  <EditorContent :editor="editor" class="editor-content focus:outline-none" />
                </div>
              </template>
            </Editor>
          </div>
        </div>
      </div>
    </Dialog>
  </div>
</template>

<style scoped>
.editor-wrapper {
  transition: border-color 0.3s ease, box-shadow 0.3s ease;
}

.editor-content {
  min-height: 120px;
  font-size: 0.95rem;
  line-height: 1.5;
}
</style>
