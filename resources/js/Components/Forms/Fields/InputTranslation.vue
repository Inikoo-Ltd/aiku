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
const initialLang =
  storedLang || Object.values(props.fieldData.languages)[0]?.code || "en"
const selectedLang = ref(initialLang)
const codeString = ref<Record<string, string>[]>([])

const loadingOne = ref(false)
const loadingAll = ref(false)

const isDisabled = computed(
  () =>
    props.fieldData?.disable ||
    !props.fieldData?.main ||
    loadingOne.value ||
    loadingAll.value
)

// ensure valid selectedLang
if (!Object.values(props.fieldData.languages).some(l => l.code === selectedLang.value)) {
  selectedLang.value = Object.values(props.fieldData.languages)[0]?.code || "en"
}

// ensure form field exists
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

const stopSocketListener = () => {
  if (channel) {
    channel.stopListening(".translate-progress")
    channel = null
  }
}

const initSocketListener = (isBulk = false, code: string) => {
  if (!window.Echo || !code) return

  const socketEvent = `translate.${code}.channel`
  const socketAction = ".translate-progress"

  stopSocketListener()

  channel = window.Echo.private(socketEvent).listen(socketAction, (eventData: any) => {
    console.log("Translation Socket Event:", eventData)

    if (eventData.translated_text) {
      if (!isBulk) {
        langBuffers.value[selectedLang.value] = eventData.translated_text
      } else {
        const langCode = Object.keys(eventData)[0]
        langBuffers.value[langCode] = eventData.translated_text
      }
    }

    // stop loading when event arrives
    if (isBulk) {
      loadingAll.value = false
    } else {
      loadingOne.value = false
    }

    notify({
      title: "Translation Completed",
      text: `Translation finished for ${langLabel(selectedLang.value)}.`,
      type: "success",
    })

    stopSocketListener()
  })
}

// === SINGLE TRANSLATION ===
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
      codeString.value = [{ [selectedLang.value]: response.data }]
      initSocketListener(false, response.data)
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

// === BULK TRANSLATION ===
const generateAllTranslationsAI = async () => {
  if (isDisabled.value) return
  loadingAll.value = true

  const langs = Object.values(props.fieldData.languages).map((l: any) => l.code)

  for (const lang of langs) {
    if (lang === props.fieldData.mainLang || langBuffers.value[lang]) continue

    try {
      const response = await axios.post(
        route("grp.models.translate", {
          languageFrom: props.fieldData.language_from || "en",
          languageTo: lang,
        }),
        { text: props.fieldData.main }
      )

      if (response.data) {
        codeString.value.push({ [lang]: response.data })
        initSocketListener(true, response.data)
      }
    } catch (error: any) {
      notify({
        title: `Translation Error for ${langLabel(lang)}`,
        text: error.response?.data?.message || "Failed to translate this language.",
        type: "error",
      })
    }
  }
}

// === WATCHERS ===
watch(
  langBuffers,
  newVal => {
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
  newLang => {
    localStorage.setItem("translation_box", newLang)
    window.dispatchEvent(new CustomEvent("translation_box_updated", { detail: newLang }))
  },
  { immediate: true }
)

// === STORAGE & CLEANUP ===
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
})

onBeforeUnmount(() => {
  stopSocketListener()
  window.removeEventListener("storage", () => {})
  window.removeEventListener("translation_box_updated", () => {})
})
</script>

<template>
  <div class="space-y-3">
    <div class="flex gap-3 px-3">
      <div class="flex flex-wrap gap-1 basis-[90%]">
        <Button
          v-for="lang in Object.values(fieldData.languages)"
          :key="lang.code"
          :label="lang.name"
          size="xxs"
          :type="selectedLang === lang.code ? 'primary' : 'gray'"
          @click="selectedLang = lang.code"
        >
          <template #icon>
            <FontAwesomeIcon
              :icon="langBuffers[lang.code] ? faCheckCircle : faCircle"
              :class="langBuffers[lang.code] ? 'text-green-500' : 'text-gray-400'"
            />
            <img v-if="lang.flag" :src="`/flags/${lang.flag}`" alt="" />
          </template>
        </Button>
      </div>

     <!--  <div class="flex justify-end items-start basis-[10%]">
        <Button
          :label="loadingAll ? 'Translating...' : 'Translate All'"
          size="xxs"
          type="rainbow"
          :icon="faRobot"
          :disabled="loadingAll || loadingOne || isDisabled"
          @click="generateAllTranslationsAI"
          :loading="loadingAll"
        />
      </div> -->
    </div>

    <div v-if="selectedLang" class="space-y-3">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div class="p-3 rounded-lg border bg-gray-50 shadow-sm">
          <p class="text-xs font-semibold text-gray-500 mb-1">
            {{ fieldData.mainLang || "en" }}
          </p>
          <p class="text-sm text-gray-700 whitespace-pre-wrap py-4">
            {{ fieldData.main }}
          </p>
        </div>

        <div class="p-3 rounded-lg border shadow-sm">
          <div class="flex justify-between items-center mb-1">
            <p class="text-xs font-semibold text-gray-500 mb-1">
              {{ langLabel(selectedLang) }}
            </p>
            <Button
              :label="loadingOne ? 'Translating...' : 'Translate'"
              size="xxs"
              type="rainbow"
              :icon="faRobot"
              :disabled="isDisabled"
              @click="generateLanguagetranslateAI"
              :loading="loadingOne"
            />
          </div>

          <input
            type="text"
            v-model="langBuffers[selectedLang]"
            class="w-full rounded-md border px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none"
            :disabled="isDisabled"
            placeholder="Enter translation..."
          />
        </div>
      </div>
    </div>
  </div>
</template>
