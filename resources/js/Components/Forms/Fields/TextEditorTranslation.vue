<script setup lang="ts">
import { ref, watch, onMounted, onBeforeUnmount, computed } from "vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import EditorV2 from "./BubleTextEditor/EditorV2.vue"
import { EditorContent } from "@tiptap/vue-3"
import { uniqueId } from "lodash"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"
import { faRobot, faCircle, faCheckCircle } from "@far"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

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

// ✅ disable state when no original content
const isDisabled = computed(() =>
  props.fieldData?.disable ||
  !props.fieldData?.main ||
  loadingOne.value ||
  loadingAll.value
)

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
  if (isDisabled.value) return
  loadingOne.value = true
  try {
    const response = await axios.post(
      route("grp.models.translate", { languageFrom: props.fieldData.language_from || 'en', languageTo: selectedLang.value }),
      { text: props.fieldData.main }
    )

    if (response.data) {
      langBuffers.value[selectedLang.value] = response.data
    }

     key.value = uniqueId("editor-")
  } catch (error: any) {
    notify({
      title: "Translation Error",
      text: error.response?.data?.message || "Failed to generate translation.",
      type: "error",
    })
  } finally {
    loadingOne.value = false
  }
}

// all translations
// all translations
const generateAllTranslationsAI = async () => {
  if (isDisabled.value) return
  loadingAll.value = true
  const langs = Object.values(props.fieldData.languages).map((l: any) => l.code)

  for (const lang of langs) {
    if (
      lang === "main" ||
      lang === props.fieldData.mainLang ||
      langBuffers.value[lang] // ✅ skip if already translated
    ) {
      continue
    }

    try {
      const response = await axios.post(
        route("grp.models.translate", {
          languageFrom: props.fieldData.language_from || "en",
          languageTo: lang,
        }),
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
    text: "All missing translations have been updated.",
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
  <div class="space-y-3">
    <div class="flex justify-end mt-3 px-3">
      <Button :label="loadingAll ? 'Translating...' : 'Translate All'" size="xxs" type="rainbow" :icon="faRobot"
        :disabled="loadingOne || loadingAll || isDisabled" @click="generateAllTranslationsAI" :loading="loadingAll" />
    </div>

    <!-- Language Selector -->
    <div class="flex flex-wrap gap-1">
      <Button v-for="lang in Object.values(fieldData.languages)" :key="lang.code + selectedLang" :label="lang.name"
        size="xxs" :type="selectedLang === lang.code ? 'primary' : 'gray'" @click="selectedLang = lang.code">
        <template #icon>
          <FontAwesomeIcon :icon="langBuffers[lang.code] ? faCheckCircle : faCircle" class="w-3.5 h-3.5"
            :class="langBuffers[lang.code] ? 'text-green-500' : 'text-gray-400'" aria-hidden="true" />
        </template>
      </Button>
    </div>

    <!-- Translation Section -->
    <div v-if="selectedLang" class="space-y-3">
      <!-- Compare: Original vs Translation -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <!-- Original -->
        <div class="p-3 rounded-lg border bg-gray-50 shadow-sm">
          <p class="text-xs font-semibold text-gray-500 mb-1">
            Original ({{ fieldData.mainLang || "en" }})
          </p>
          <div class="text-sm text-gray-700 whitespace-pre-wrap py-4"
            v-html="fieldData.main || '<span class=text-gray-400>No content available</span>'" />
        </div>


        <!-- Translation -->
        <div class="p-3 rounded-lg border shadow-sm">
          <div class="flex justify-between items-center mb-1">
            <p class="text-xs font-semibold text-gray-500 mb-1">
              {{ langLabel(selectedLang) }}
            </p>
            <Button :label="loadingOne ? 'Generating...' : 'Generate AI'" size="xxs" type="rainbow" :icon="faRobot"
              :disabled="loadingOne || loadingAll || isDisabled" @click="generateLanguagetranslateAI"   :loading="loadingOne"/>
          </div>

          <EditorV2 v-model="langBuffers[selectedLang]" :key="selectedLang + key">
            <template #editor-content="{ editor }">
              <div
                class="editor-wrapper border border-gray-300 rounded-md bg-white p-3 focus-within:border-blue-400 transition-all"
                :class="{ 'opacity-50 pointer-events-none': isDisabled }">
                <EditorContent :key="key" :editor="editor"
                  class="editor-content focus:outline-none leading-6 min-h-[6rem]" />
              </div>
            </template>
          </EditorV2>
        </div>
      </div>
    </div>
  </div>
</template>
