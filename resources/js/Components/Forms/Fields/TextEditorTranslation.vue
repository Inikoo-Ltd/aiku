<script setup lang="ts">
import { ref, watch, onMounted, onBeforeUnmount, computed } from "vue"
import { ulid } from "ulid"
import EditorV2 from "./BubleTextEditor/EditorV2.vue"
import { EditorContent } from "@tiptap/vue-3"

import axios from "axios"
import { notify } from "@kyvg/vue3-notification"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { trans } from "laravel-vue-i18n"
import { faLanguage } from "@far"
import { faMale } from "@fas"
import { faOctopusDeploy } from "@fortawesome/free-brands-svg-icons"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"

interface Language {
  code: string
  [key: string]: any
}

const props = defineProps<{
  form: Record<string, any>
  fieldName: string
  fieldData: {
    main?: string
    reviewed?: boolean
    disable?: boolean
    language_from?: string
    language_to?: string
    languages: Record<string, Language>
  }
}>()

const emits = defineEmits()

const loading = ref(false)
const key = ref(ulid())


const languagesTo = ref<Language>(
  Object.values(props.fieldData.languages).find(
    (l: Language) => l.code === props.fieldData.language_to
  ) || Object.values(props.fieldData.languages)[0]
)

if (typeof props.form[props.fieldName] !== "string") {
  props.form[props.fieldName] = ""
}


const isDisabled = computed(() =>
  props.fieldData.disable || !props.fieldData.main || loading.value
)


const langLabel = computed(() => languagesTo.value.code)


const generateTranslateAI = async () => {
  if (isDisabled.value) return

  loading.value = true
  try {
    const { data } = await axios.post(
      route("grp.models.translate", {
        languageFrom: props.fieldData.language_from || "en",
        languageTo: languagesTo.value.code || "en",
      }),
      { text: props.fieldData.main }
    )

    if (data) {
      props.form[props.fieldName] = data
      emits("update:form", { ...props.form })
      key.value = ulid()

      notify({
        title: trans("Translation Completed"),
        text: trans("Translation generated successfully."),
        type: "success",
      })
    }
  } catch (error: any) {
    notify({
      title: trans("Translation Error"),
      text: error.response?.data?.message || trans("Failed to generate translation."),
      type: "error",
    })
  } finally {
    loading.value = false
  }
}
</script>


<template>
  <div class="space-y-3">
    <div class="space-y-2">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-2">

        <div class="p-3 rounded-md border bg-white flex gap-3 items-start">

          <!-- Icon -->
          <div class="h-6 w-6 flex items-center justify-center
           rounded-md bg-indigo-100 text-[#4B0082] shrink-0 mt-1">
            <FontAwesomeIcon :icon="faOctopusDeploy" v-tooltip="trans('master data of ') + props.fieldName"
              class="h-3.5 w-3.5" />
          </div>

          <!-- Content -->
          <div class="text-sm text-gray-700 whitespace-pre-wrap leading-6 flex-1 bg-gray-50 p-4 rounded-md border"
            v-html="fieldData.main" />
        </div>

        <div class="relative p-3 rounded-md border bg-white">
          <div class="
         flex gap-3 items-start">

            <p class="text-[11px] font-semibold text-gray-500
           uppercase tracking-wide shrink-0 pt-1" v-tooltip="languagesTo?.name">
              {{ langLabel }} 
            </p>

            <div class="flex-1 pr-10  rounded-md  ">
              <div class="bg-gray-50 border p-2 rounded-md">
                <EditorV2 v-model="props.form[props.fieldName]" :key="key">
                  <template #editor-content="{ editor }">
                    <EditorContent :editor="editor" class="focus:outline-none text-sm text-gray-700
                 whitespace-pre-wrap leading-6 min-h-[5rem]" />
                  </template>
                </EditorV2>
              </div>
              <div class="grid grid-flow-col text-xs italic text-gray-500 mt-2 space-x-12 justify-start tabular-nums">
                <p class="">{{ trans('Characters') }}: {{ form[fieldName]?.length ?? 0 }}</p>
                <p class="">
                  {{ trans('Words') }}: {{ form[fieldName]?.trim().split(/\s+/).filter(Boolean).length ?? 0 }}
                </p>
              </div>
            </div>


            <button v-if="fieldData.reviewed" type="button" disabled class="
           h-6 w-6 flex items-center justify-center
           rounded-md bg-white text-gray-600 button-primary
           shadow-sm" v-tooltip="trans('already review it by user')">
              <FontAwesomeIcon :icon="faMale" class="h-3.5 w-3.5" />
            </button>

            <button v-if="fieldData.main" type="button" class="h-6 w-6 flex items-center justify-center
           rounded-md bg-white hover:bg-gray-300 border
           disabled:opacity-50 disabled:pointer-events-none
           shrink-0 transition mt-0.5" @click="generateTranslateAI" :disabled="isDisabled || loading"
              v-tooltip="trans('Get translation from AI')">
              <LoadingIcon v-if="loading" class="h-3.5 w-3.5 animate-spin" />
              <FontAwesomeIcon v-else :icon="faLanguage" class="h-3.5 w-3.5" />
            </button>

          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<style scoped>
.button-primary {
  color: var(--theme-color-4) !important
}
</style>
