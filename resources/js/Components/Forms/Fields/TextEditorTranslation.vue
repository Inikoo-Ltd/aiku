<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount } from "vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { camelCase } from "lodash-es"
import EditorV2 from "./BubleTextEditor/EditorV2.vue";
import { EditorContent } from '@tiptap/vue-3'

const props = defineProps<{
  form: any
  fieldName: string
  fieldData: any
}>()

const emits = defineEmits(["update:form"])

// Init
const storedLang = localStorage.getItem("translation_box")
const initialLang = storedLang || Object.values(props.fieldData.languages)[0]?.code || "en"
const selectedLang = ref(initialLang)

// Ensure valid language
if (!Object.values(props.fieldData.languages).some(l => l.code === selectedLang.value)) {
  selectedLang.value = Object.values(props.fieldData.languages)[0]?.code || "en"
}

// Ensure translate object exists
if (!props.form[props.fieldName].translate) {
  props.form[props.fieldName].translate = { value: {} }
}
if (!props.form[props.fieldName].translate.value) {
  props.form[props.fieldName].translate.value = {}
}

// Local buffer
const langBuffers = ref<Record<string, string>>({
  ...props.form[props.fieldName].translate.value
})

// Helper
const langLabel = (code: string) => {
  const langObj = Object.values(props.fieldData.languages).find(
    (l: any) => l.code === code
  )
  return langObj?.name ?? code
}

// Sync buffer → form
watch(
  langBuffers,
  (newVal) => {
    props.form[props.fieldName].translate.value = { ...newVal }
    emits("update:form", { ...props.form })
  },
  { deep: true }
)

// Sync selectedLang → localStorage + custom event
watch(
  selectedLang,
  (newLang) => {
    localStorage.setItem("translation_box", newLang)
    // 🔥 Custom event for same-tab sync
    window.dispatchEvent(new CustomEvent("translation_box_updated", { detail: newLang }))
  },
  { immediate: true }
)

// Listen to cross-tab + same-tab updates
onMounted(() => {
  const handleStorage = (e: StorageEvent) => {
    if (e.key === "translation_box" && e.newValue) {
      selectedLang.value = e.newValue
    }
  }

  const handleCustom = (e: CustomEvent) => {
    selectedLang.value = e.detail
  }

  window.addEventListener("storage", handleStorage) // cross-tab
  window.addEventListener("translation_box_updated", handleCustom as EventListener) // same-tab

  onBeforeUnmount(() => {
    window.removeEventListener("storage", handleStorage)
    window.removeEventListener("translation_box_updated", handleCustom as EventListener)
  })
})
</script>

<template>
  <div class="space-y-4">
    <!-- Master Field -->
    <div>
      <label class="block mb-1 font-medium text-sm">
        {{ fieldData.label }} (Master)
      </label>
      <EditorV2 v-model="form[fieldName].master.value">
        <template #editor-content="{ editor }">
          <div
            class="editor-wrapper border border-gray-300 rounded-md bg-white p-3 focus-within:border-blue-400 transition-all">
            <EditorContent :editor="editor" class="editor-content focus:outline-none" />
          </div>
        </template>
      </EditorV2>
    </div>

    <!-- Language Selector -->
    <div class="flex flex-wrap gap-2">
      <Button v-for="lang in Object.values(fieldData.languages)" :key="lang.code + selectedLang" :label="lang.name"
        size="xxs" :type="selectedLang === lang.code ? 'primary' : 'tertiary'" @click="selectedLang = lang.code" />
    </div>

    <!-- Translation Input -->
    <div v-if="selectedLang" class="mt-3">
      <label class="block mb-1 font-medium text-sm">
        Translation to {{ langLabel(selectedLang) }}
      </label>


      <EditorV2 v-model="langBuffers[selectedLang]">
        <template #editor-content="{ editor }">
          <div
            class="editor-wrapper border border-gray-300 rounded-md bg-white p-3 focus-within:border-blue-400 transition-all">
            <EditorContent :editor="editor" class="editor-content focus:outline-none" />
          </div>
        </template>
      </EditorV2>
    </div>
  </div>
</template>
