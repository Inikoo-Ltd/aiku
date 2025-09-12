<script setup lang="ts">
import { ref, watch, onMounted, onBeforeUnmount, computed } from "vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
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

const storedLang = localStorage.getItem("translation_box")
const initialLang = storedLang || Object.values(props.fieldData.languages)[0]?.code || "en"
const selectedLang = ref(initialLang)

// Loading states
const loadingOne = ref(false)
const loadingAll = ref(false)

// disable logic (prop + main null + loading)
const isDisabled = computed(() =>
  props.fieldData?.disable ||
  !props.fieldData?.main ||
  loadingOne.value ||
  loadingAll.value
)


if (!Object.values(props.fieldData.languages).some(l => l.code === selectedLang.value)) {
  selectedLang.value = Object.values(props.fieldData.languages)[0]?.code || "en"
}

if (!props.form[props.fieldName]) {
  props.form[props.fieldName] = {}
}

const langBuffers = ref<Record<string, string>>({
  ...props.form[props.fieldName]
})

const langLabel = (code: string) => {
  const langObj = Object.values(props.fieldData.languages).find(
    (l: any) => l.code === code
  )
  return langObj?.name ?? code
}

watch(
  langBuffers,
  (newVal) => {
    props.form[props.fieldName] = { ...newVal }
    emits("update:form", { ...props.form })
  },
  { deep: true }
)

watch(
  selectedLang,
  (newLang) => {
    localStorage.setItem("translation_box", newLang)
    window.dispatchEvent(new CustomEvent("translation_box_updated", { detail: newLang }))
  },
  { immediate: true }
)

// Single translation
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

// Translate ALL
const generateAllTranslationsAI = async () => {
  if (isDisabled.value) return
  loadingAll.value = true
  const langs = Object.values(props.fieldData.languages).map((l: any) => l.code)

  for (const lang of langs) {
    if (lang === "main" || lang === props.fieldData.mainLang) continue

    try {
      const response = await axios.post(
        route("grp.models.translate", { languageFrom: props.fieldData.language_from || 'en', languageTo: lang }),
        { text: props.fieldData.main }
      )
      if (response.data) {
        langBuffers.value[lang] = response.data
      }
    } catch (error: any) {
      notify({
        title: `Translation Error for ${langLabel(lang)}`,
        text: error.response?.data?.message || "Failed to translate this language.",
        type: "error",
      })
    }
  }

  loadingAll.value = false

  notify({
    title: "Translation Complete",
    text: "All available translations have been updated.",
    type: "success",
  })
}

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
    <div class="flex justify-end mt-3  px-3">
      <Button :label="loadingAll ? 'Translating...' : 'Translate All'" size="xxs" type="rainbow" :icon="faRobot"
        :disabled="isDisabled" @click="generateAllTranslationsAI" />
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
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <!-- Original -->
        <div class="p-3 rounded-lg border bg-gray-50 shadow-sm">
          <p class="text-xs font-semibold text-gray-500 mb-1">
            Original ({{ fieldData.mainLang || "en" }})
          </p>
          <p class="text-sm text-gray-700 whitespace-pre-wrap py-4">
            {{ fieldData.main ? fieldData.main : 'No content available' }}
          </p>
        </div>

        <!-- Translation -->
        <div class="p-3 rounded-lg border shadow-sm">
          <div class="flex justify-between items-center mb-1">
            <p class="text-xs font-semibold text-gray-500 mb-1">
              {{ langLabel(selectedLang) }}
            </p>
            <Button :label="loadingOne ? 'Generating...' : 'Generate AI'" size="xxs" type="rainbow" :icon="faRobot"
              :disabled="isDisabled" @click="generateLanguagetranslateAI" />
          </div>

          <input type="text" v-model="langBuffers[selectedLang]"
            class="w-full rounded-md border px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none"
            :disabled="isDisabled" placeholder="Enter translation..." />
        </div>
      </div>
    </div>
  </div>
</template>
