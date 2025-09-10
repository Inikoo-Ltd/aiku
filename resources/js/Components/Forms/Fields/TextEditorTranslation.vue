<script setup lang="ts">
import { ref, watch, onMounted, onBeforeUnmount } from "vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import EditorV2 from "./BubleTextEditor/EditorV2.vue"
import { EditorContent } from "@tiptap/vue-3"
import { faRobot } from "@far"
import { uniqueId } from "lodash"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"

const props = defineProps<{
  form: any
  fieldName: string
  fieldData: any
}>()

const emits = defineEmits(["update:form"])
const key = ref(uniqueId("editor-"))

const storedLang = localStorage.getItem("translation_box")
const initialLang =
  storedLang || Object.values(props.fieldData.languages)[0]?.code || "en"
const selectedLang = ref(initialLang)

// loading states
const loadingOne = ref(false)
const loadingAll = ref(false)

// Ensure valid lang
if (!Object.values(props.fieldData.languages).some(l => l.code === selectedLang.value)) {
  selectedLang.value = Object.values(props.fieldData.languages)[0]?.code || "en"
}

// Ensure form field exists
if (!props.form[props.fieldName]) {
  props.form[props.fieldName] = {}
}

// Local buffer (copy all existing translations)
const langBuffers = ref<Record<string, string>>({
  ...props.form[props.fieldName],
})

const langLabel = (code: string) => {
  const langObj = Object.values(props.fieldData.languages).find(
    (l: any) => l.code === code
  )
  return langObj?.name ?? code
}

// single translation
const generateLanguagetranslateAI = async () => {
  loadingOne.value = true
  try {
    const response = await axios.post(
      route("grp.models.translate", { language: selectedLang.value }),
      { text: props.fieldData.main }
    )

    if (response.data) {
      langBuffers.value[selectedLang.value] = response.data
      key.value = uniqueId("editor-") // force re-render
    }
  } catch (error: any) {
    notify({
      title: "Translation Error",
      text:
        error.response?.data?.message ||
        "Failed to generate translation. Please try again.",
      type: "error",
    })
  } finally {
    loadingOne.value = false
  }
}

// all translations
const generateAllTranslationsAI = async () => {
  loadingAll.value = true
  const langs = Object.values(props.fieldData.languages).map((l: any) => l.code)

  for (const lang of langs) {
    if (lang === "main" || lang === props.fieldData.mainLang) continue

    try {
      const response = await axios.post(
        route("grp.models.translate", { language: lang }),
        { text: props.fieldData.main }
      )

      if (response.data) {
        langBuffers.value[lang] = response.data
        key.value = uniqueId("editor-")
      }
    } catch (error: any) {
      notify({
        title: `Translation Error for ${langLabel(lang)}`,
        text:
          error.response?.data?.message ||
          "Failed to translate this language.",
        type: "error",
      })
    }
  }

  loadingAll.value = false
  key.value = uniqueId("editor-")
  notify({
    title: "Translation Complete",
    text: "All available translations have been updated.",
    type: "success",
  })
}

// sync buffer → form
watch(
  langBuffers,
  newVal => {
    props.form[props.fieldName] = { ...newVal }
    emits("update:form", { ...props.form })
  },
  { deep: true }
)

// sync selectedLang → storage + event
watch(
  selectedLang,
  newLang => {
    localStorage.setItem("translation_box", newLang)
    window.dispatchEvent(
      new CustomEvent("translation_box_updated", { detail: newLang })
    )
  },
  { immediate: true }
)

// Listen cross-tab
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
      <div class="flex justify-between items-center mb-1">
        <label class="block mb-1 font-medium text-sm">
          Translation to {{ langLabel(selectedLang) }}
        </label>

        <div class="flex gap-2">
          <!-- Single -->
          <Button
            :label="loadingOne ? 'Generating...' : 'Generate from AI'"
            size="xxs"
            type="gray"
            :icon="faRobot"
            :disabled="loadingOne || loadingAll"
            @click="generateLanguagetranslateAI"
          />

          <!-- All -->
          <Button
            :label="loadingAll ? 'Translating All...' : 'Translate All'"
            size="xxs"
            type="primary"
            :icon="faRobot"
            :disabled="loadingOne || loadingAll"
            @click="generateAllTranslationsAI"
          />
        </div>
      </div>

      <EditorV2 v-model="langBuffers[selectedLang]" :key="selectedLang + key">
        <template #editor-content="{ editor }">
          <div
            class="editor-wrapper border border-gray-300 rounded-md bg-white p-3 focus-within:border-blue-400 transition-all"
          >
            <EditorContent
              :key="key"
              :editor="editor"
              class="editor-content focus:outline-none leading-6 min-h-[6rem]"
            />
          </div>
        </template>
      </EditorV2>
    </div>
  </div>
</template>
