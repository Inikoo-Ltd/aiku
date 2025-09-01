<script setup lang="ts">
import { ref, watch, onMounted, onBeforeUnmount } from "vue"
import Button from "@/Components/Elements/Buttons/Button.vue"

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

// Ensure form field exists
if (!props.form[props.fieldName]) {
  props.form[props.fieldName] = {}
}

// Local buffer (copy all existing translations)
const langBuffers = ref<Record<string, string>>({
  ...props.form[props.fieldName]
})

// Helper
const langLabel = (code: string) => {
  const langObj = Object.values(props.fieldData.languages).find(
    (l: any) => l.code === code
  )
  return langObj?.name ?? code
}

// Sync buffer → form (always push updated values back)
watch(
  langBuffers,
  (newVal) => {
    props.form[props.fieldName] = { ...newVal }
    emits("update:form", { ...props.form })
  },
  { deep: true }
)

// Sync selectedLang → localStorage + custom event
watch(
  selectedLang,
  (newLang) => {
    localStorage.setItem("translation_box", newLang)
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

  window.addEventListener("storage", handleStorage)
  window.addEventListener("translation_box_updated", handleCustom as EventListener)

  onBeforeUnmount(() => {
    window.removeEventListener("storage", handleStorage)
    window.removeEventListener("translation_box_updated", handleCustom as EventListener)
  })
})
</script>

<template>
  <div class="space-y-4">
    <!-- Language Selector -->
    <div class="flex flex-wrap gap-2">
      <Button
        v-for="lang in Object.values(fieldData.languages)"
        :key="lang.code + selectedLang"
        :label="lang.name"
        size="xxs"
        :type="selectedLang === lang.code ? 'primary' : 'tertiary'"
        @click="selectedLang = lang.code"
      />
    </div>

    <!-- Translation Input -->
    <div v-if="selectedLang" class="mt-3">
      <label class="block mb-1 font-medium text-sm">
        Translation to {{ langLabel(selectedLang) }}
      </label>
      <input
        type="text"
        v-model="langBuffers[selectedLang]"
        class="w-full rounded-lg border px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:outline-none"
      />
    </div>
  </div>
</template>
