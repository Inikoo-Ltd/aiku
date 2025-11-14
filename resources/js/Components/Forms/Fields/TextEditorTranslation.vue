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

const storedLang = localStorage.getItem("translation_box")
const initialLang = storedLang || Object.values(props.fieldData.languages)[0]?.code || "en"
const selectedLang = ref(initialLang)
const codeString = ref<string | null>(null)
const key = ref(uniqueId("editor-"))

const loadingOne = ref(false)
const loadingAll = ref(false)

const isDisabled = computed(
  () =>
    props.fieldData?.disable ||
    !props.fieldData?.main ||
    loadingOne.value ||
    loadingAll.value
)

if (!Object.values(props.fieldData.languages).some((l) => l.code === selectedLang.value)) {
  selectedLang.value = Object.values(props.fieldData.languages)[0]?.code || "en"
}

if (!props.form[props.fieldName]) {
  props.form[props.fieldName] = {}
}

const langBuffers = ref<Record<string, string>>({
  ...props.form[props.fieldName],
})

const langLabel = (code: string) => {
  const langObj = Object.values(props.fieldData.languages).find(
    (l: any) => l.code === code
  )
  return langObj?.name ?? code
}

let channel: any = null
const initSocketListener = (isBulk = false) => {
  if (!window.Echo || !codeString.value) return

  const socketEvent = `translate.${codeString.value}.channel`
  const socketAction = ".translate-progress"

  if (channel) channel.stopListening(socketAction)

  channel = window.Echo.private(socketEvent).listen(socketAction, (eventData: any) => {
    console.log("Translation Socket Event:", eventData)

    if (eventData.translated_text) {
      langBuffers.value[selectedLang.value] = eventData.translated_text
      key.value = uniqueId("editor-")
    }

    // ✅ Stop loading when translation event arrives
    if (isBulk) {
      loadingAll.value = false
    } else {
      loadingOne.value = false
    }

    // optional notification
    notify({
      title: "Translation Completed",
      text: `Translation finished for ${langLabel(selectedLang.value)}.`,
      type: "success",
    })

    // stop listening after this event
    channel.stopListening(socketAction)
  })
}

// === TRANSLATE ONE LANGUAGE ===
const generateLanguagetranslateAI = async () => {
  if (isDisabled.value) return
  loadingOne.value = true

  try {
    const response = await axios.post(
      route("grp.models.translate", {
        languageFrom: props.fieldData.language_from || "en",
        languageTo: selectedLang.value,
      }),
      { text: props.fieldData.main }
    )

    if (response.data) {
      codeString.value = response.data
      initSocketListener(false) // single translation
    }
  } catch (error: any) {
    loadingOne.value = false
    notify({
      title: "Translation Error",
      text: error.response?.data?.message || "Failed to generate translation.",
      type: "error",
    })
  }
}

// === TRANSLATE ALL LANGUAGES ===
const generateAllTranslationsAI = async () => {
  if (isDisabled.value) return
  loadingAll.value = true

  const langs = Object.values(props.fieldData.languages).map((l: any) => l.code)

  for (const lang of langs) {
    if (lang === "main" || lang === props.fieldData.mainLang || langBuffers.value[lang]) continue

    try {
      const response = await axios.post(
        route("grp.models.translate", {
          languageFrom: props.fieldData.language_from || "en",
          languageTo: lang,
        }),
        { text: props.fieldData.main }
      )

      if (response.data) {
        langBuffers.value[lang] = response.data.translation
        if (response.data.code) {
          codeString.value = response.data.code
          initSocketListener(true) // bulk translation
        }
      }
    } catch (error: any) {
      notify({
        title: `Translation Error for ${langLabel(lang)}`,
        text: error.response?.data?.message || "Failed to translate this language.",
        type: "error",
      })
    }
  }

  // ❌ don't set loadingAll=false here, handled in socket event
}

// === WATCHERS ===
watch(
  langBuffers,
  (newVal) => {
    if (props.fieldData.mode === "single") {
      props.form[props.fieldName] = newVal[selectedLang.value] || ""
    } else {
      props.form[props.fieldName] = { ...newVal }
    }
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

// === STORAGE & SOCKET SYNC ===
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

  if (codeString.value) {
    initSocketListener()
  }

  watch(codeString, (newCode) => {
    if (newCode) {
      initSocketListener()
    }
  })

  onBeforeUnmount(() => {
    window.removeEventListener("storage", handleStorage)
    window.removeEventListener("translation_box_updated", handleCustom as EventListener)
    if (channel) channel.stopListening(".translate-progress")
  })
})
</script>

<template>
  <div class="space-y-3">
    <!-- Language Selector -->
    <div class="flex gap-3 px-3">
      <!-- Language buttons (kiri 50%) -->
      <div class="flex flex-wrap gap-1 basis-[90%]">
        <Button v-for="lang in Object.values(fieldData.languages)" :key="lang.code + selectedLang" :label="lang.name"
          size="xxs" :type="selectedLang === lang.code ? 'primary' : 'gray'" @click="selectedLang = lang.code">
          <template #icon>
            <FontAwesomeIcon :icon="langBuffers[lang.code] ? faCheckCircle : faCircle"
              :class="langBuffers[lang.code] ? 'text-green-500' : 'text-gray-400'" aria-hidden="true" />
            <img v-if="lang.flag" :src="`/flags/${lang.flag}`" alt="" />
          </template>
        </Button>
      </div>

      <!-- Translate All button (kanan 50%) -->
   <!--    <div class="flex justify-end items-start basis-[10%]">
        <Button :label="loadingAll ? 'Translating...' : 'Translate All'" size="xxs" type="rainbow" :icon="faRobot"
          :disabled="loadingAll || loadingOne || isDisabled" @click="generateAllTranslationsAI" :loading="loadingAll" />
      </div> -->
    </div>


    <!-- Translation Section -->
    <div v-if="selectedLang" class="space-y-3">
      <!-- Compare: Original vs Translation -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <!-- Original -->
        <div class="p-3 rounded-lg border bg-gray-50 shadow-sm">
          <p class="text-xs font-semibold text-gray-500 mb-1">
            {{ fieldData.mainLang || "en" }}
          </p>
          <div class="text-sm text-gray-700 whitespace-pre-wrap py-4" v-html="fieldData.main" />
        </div>


        <!-- Translation -->
        <div class="p-3 rounded-lg border shadow-sm">
          <div class="flex justify-between items-center mb-1">
            <p class="text-xs font-semibold text-gray-500 mb-1">
              {{ langLabel(selectedLang) }}
            </p>
            <Button :label="loadingOne ? 'Generating...' : 'Generate AI'" size="xxs" type="rainbow" :icon="faRobot"
              :disabled="loadingOne || loadingAll || isDisabled" @click="generateLanguagetranslateAI"
              :loading="loadingOne" />
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
