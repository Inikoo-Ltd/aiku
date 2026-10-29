<script setup lang="ts">
import { ref, watch, onMounted, onBeforeUnmount, computed } from "vue"
import { ulid } from "ulid"
import EditorV2 from "./BubleTextEditor/EditorV2.vue"
import { EditorContent } from "@tiptap/vue-3"
import EditorWritingQualityCheck from "@/Components/WritingQuality/EditorWritingQualityCheck.vue"

import axios from "axios"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { ctrans } from "@/Composables/useTrans"
import TranslationRating from "@/Components/Forms/Fields/TranslationRating.vue"
import { faLanguage } from "@far"
import { faMale } from "@fas"
import { faOctopusDeploy } from "@fortawesome/free-brands-svg-icons"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import Toggle from "@/Components/Pure/Toggle.vue"
import { get } from 'lodash-es'

interface Language {
  code: string
  [key: string]: any
}
defineOptions({ inheritAttrs: false })

const props = defineProps<{
  form: Record<string, any>
  fieldName: string
  fieldData: {
    main?: string
    reviewed?: boolean
    value?: string
    review_route?: { name: string; parameters: Record<string, unknown> }
    disable?: boolean
    language_from?: string
    language_to?: string
    show_follow_master?: boolean
    follow_master?: boolean
    languages: Record<string, Language>
    toggle?: string[]
    routeGetInternalLink : routeType
  }
  updateRoute: {
    name: string
    parameters: []
  }
}>()

const emits = defineEmits()

const loading = ref(false)
const editorError = ref<string | null>(null)
const followMasterError = ref<string | null>(null)
const key = ref(ulid())

watch(() => props.fieldData.value, () => {
  key.value = ulid()
})


const languagesTo = ref<Language>(
  Object.values(props.fieldData.languages).find(
    (l: Language) => l.code === props.fieldData.language_to
  ) || Object.values(props.fieldData.languages)[0]
)

if (typeof props.form[props.fieldName] !== "string") {
  props.form[props.fieldName] = ""
  props.form.defaults?.(props.fieldName, "")
}


const isDisabled = computed(() =>
  props.fieldData.disable || !props.fieldData.main || loading.value
)


const langLabel = computed(() => languagesTo.value.code)

const isLoadingFollowMaster = ref(false);

const capitalizeFirstLetter = (text: string) => {
  if (text.length === 0) {
    return "";
  }
  return text.charAt(0).toUpperCase() + text.slice(1);
}

const changeValue = (async () => {
  props.fieldData.follow_master = !props.fieldData.follow_master;
  isLoadingFollowMaster.value = true;
  followMasterError.value = null;
  await axios.patch(route(props.updateRoute.name, props.updateRoute.parameters), {
      ["follow_master_"+props.fieldName]: props.fieldData.follow_master
  }).catch((error: any) => {
    props.fieldData.follow_master = !props.fieldData.follow_master;
    followMasterError.value = error.response?.data?.message || ctrans('Could not change whether :_fieldname follows master', {_fieldname: props.fieldName});
  }).finally(() => {
    isLoadingFollowMaster.value = false;
  });
})

const generateTranslateAI = async () => {
  if (isDisabled.value) return

  loading.value = true
  editorError.value = null
  try {
    const { data } = await axios.post(
      route("grp.models.translate", {
        languageFrom: props.fieldData.language_from || "en",
        languageTo: languagesTo.value.code || "en",
      }),
      { text: props.fieldData.main, catalogue: true }
    )

    if (data) {
      props.form[props.fieldName] = data
      emits("update:form", { ...props.form })
      key.value = ulid()
    }
  } catch (error: any) {
    editorError.value = error.response?.data?.message || ctrans("Failed to generate translation.")
  } finally {
    loading.value = false
  }
}

const countWords = (value?: string | null) => {
  const text = (value ?? '')
    .replace(/&nbsp;/gi, ' ')
    .trim()

  return text
    ? text.split(' ').length
    : 0
}
</script>


<template>
  <p v-if="get(form, ['errors', `${fieldName}`])" class="text-sm text-red-600 bold pb-1" :id="`${fieldName}-error`">
    {{ form.errors[fieldName] }}
  </p>
  <div class="space-y-3">
    <div class="space-y-2">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
        <div  v-if="fieldData.show_follow_master" class="px-3 py-1 flex col-span-2 items-end justify-items-center align-middle w-full">
          <span class="align-middle h-full w-full text-end mr-3 font-semibold">
            {{ ctrans('Follow Master') }}
          </span>
          <Toggle
            v-tooltip="ctrans('Turning this option on would make it so that this item will follow its master counterpart')"
            :modelValue="fieldData.follow_master"
            @update:modelValue="changeValue()"
            :loading="isLoadingFollowMaster"
          >
          </Toggle>
        </div>
        <p v-if="followMasterError" class="col-span-2 px-3 text-end text-xs text-red-600">{{ followMasterError }}</p>
        <div class="p-3 rounded-md border bg-white flex gap-3 items-start">

          <!-- Icon -->
          <div class="h-6 w-6 flex items-center justify-center
           rounded-md bg-indigo-100 text-[#4B0082] shrink-0 mt-1">
            <FontAwesomeIcon :icon="faOctopusDeploy" v-tooltip="ctrans(':_fieldName of the Master', {_fieldName: capitalizeFirstLetter(props.fieldName)})"
              class="h-3.5 w-3.5" fixed-width />
          </div>

          <!-- Content -->
          <EditorV2 v-if="fieldData.main" v-model="fieldData.main" :toggle="fieldData.toggle" class="text-sm text-gray-700 whitespace-pre-wrap leading-6 flex-1 bg-gray-50 p-4 rounded-md border" :editable="false">
            <template #editor-content="{ editor }">
              <EditorContent :editor="editor" class="focus:outline-none text-sm text-gray-700 whitespace-pre-wrap leading-6 min-h-[5rem]" :readonly="true"/>
            </template>
          </EditorV2>
          <div v-else class="text-sm text-gray-700 whitespace-pre-wrap leading-6 flex-1 bg-gray-50 p-4 rounded-md border"
            v-html="fieldData.main" />
          <div class="h-6 w-6 flex items-center justify-center
           rounded-md bg-indigo-100 text-[#4B0082] shrink-0 mt-1">
            <FontAwesomeIcon :icon="faOctopusDeploy" v-tooltip="ctrans(':_fieldName of the Master', {_fieldName: capitalizeFirstLetter(props.fieldName)})"
              class="h-3.5 w-3.5" fixed-width />
          </div>
        </div>

        <div class="relative p-3 rounded-md border bg-white">
          <div class="
         flex gap-3 items-start">

            <p class="text-[11px] font-semibold text-gray-500
           uppercase tracking-wide shrink-0 pt-1" v-tooltip="languagesTo?.name">
              {{ langLabel }} 
            </p>

            <div class="flex-1 pr-10  rounded-md  ">
              <div class="bg-gray-50 border p-2 rounded-md" :class="get(form, ['errors', `${fieldName}`]) ? 'border-red-500' : ''">
                <EditorV2 v-model="props.form[props.fieldName]" :key="key" :toggle="fieldData.toggle" :routeGetInternalLink="fieldData.routeGetInternalLink">
                  <template #editor-content="{ editor }">
                    <EditorContent :editor="editor" class="focus:outline-none text-sm text-gray-700
                 whitespace-pre-wrap leading-6 min-h-[5rem]" />
                    <EditorWritingQualityCheck :editor="editor" context="product_description" class="mt-2 border-t pt-2" />
                  </template>
                </EditorV2>
              </div>
              <p v-if="editorError" class="mt-1 text-xs text-red-600">{{ editorError }}</p>
              <div class="grid grid-flow-col text-xs italic text-gray-500 mt-2 space-x-12 justify-start tabular-nums">
                <p class="">{{ ctrans('Characters') }}: {{ form[fieldName]?.length ?? 0 }}</p>
                <p class="">
                  {{ ctrans('Words') }}:   {{ countWords(form[fieldName]) }}
                </p>
              </div>
            </div>


            <button v-if="fieldData.reviewed" type="button" disabled class="
              h-6 w-6 flex items-center justify-center
              rounded-md bg-white text-gray-600 button-primary
              shadow-sm" v-tooltip="ctrans('Already reviewed by user')">
              <FontAwesomeIcon :icon="faMale" class="h-3.5 w-3.5" fixed-width />
            </button>

            <button v-if="fieldData.main" type="button" class="h-6 w-6 flex items-center justify-center
              rounded-md bg-white hover:bg-gray-300 border
              disabled:opacity-50 disabled:pointer-events-none
              shrink-0 transition mt-0.5" @click="generateTranslateAI" :disabled="isDisabled || loading"
              v-tooltip="ctrans('Get translation from AI')"
            >
              <LoadingIcon v-if="loading" class="h-3.5 w-3.5 animate-spin" />
              <FontAwesomeIcon v-else :icon="faLanguage" class="h-3.5 w-3.5" fixed-width />
            </button>

          </div>
          <TranslationRating
            v-if="fieldData.review_route && !fieldData.reviewed && fieldData.value"
            class="mt-2"
            :field="fieldName"
            :reviewRoute="fieldData.review_route"
          />
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
