<script setup lang="ts">
import PureInput from "@/Components/Pure/PureInput.vue"
import { ref, watch, computed, inject } from "vue"
import { get } from "lodash-es"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faExclamationCircle, faCheckCircle, faCopy } from '@fas'
import { library } from "@fortawesome/fontawesome-svg-core"
import Dialog from 'primevue/dialog'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { faLanguage } from "@fal"

library.add(faExclamationCircle, faCheckCircle, faCopy)

const locale = inject('locale', aikuLocaleStructure)
const languageOptions = locale.languageOptions

const props = defineProps<{
  form: any
  fieldName: string
  fieldData: {
    type: string
    label: string
    value: {
      [lang: string]: {
        value: string
        default: boolean
      }
    }
  }
}>()

const emits = defineEmits<{
  (e: 'update:form', form: any): void
}>()

const showModal = ref(false)

const updateLangValue = (lang: string, newValue: string) => {
  const updated = { ...props.form[props.fieldName] }
  if (updated[lang]) {
    updated[lang].value = newValue
    props.form[props.fieldName] = updated
    props.form.errors[`${props.fieldName}.${lang}.value`] = ''
    emits('update:form', props.form)
  }
}

const defaultLangs = computed(() => {
  return Object.entries(props.fieldData.value)
    .filter(([_, data]: any) => data.default)
})

const allLangs = computed(() => {
  return Object.entries(props.fieldData.value)
})

const langName = (code: string): string => {
  return Object.values(languageOptions).find(lang => lang.code === code)?.name ?? code.toUpperCase()
}
</script>
<template>
  <div class="space-y-4">
    <!-- Default language input -->
    <div
      v-for="[lang, langData] in defaultLangs"
      :key="lang"
      class="relative"
    >
      <div class="flex w-full rounded-md shadow-sm overflow-hidden border border-gray-300 focus-within:ring-1 focus-within:ring-blue-500">
        <span
          class="inline-flex items-center px-3 bg-gray-100 border-r border-gray-300 text-gray-600 text-sm font-medium"
        >
          {{ lang }}
        </span>
        <PureInput
          class="flex-1 border-0 rounded-none focus:ring-0"
          :modelValue="form[fieldName][lang].value"
          @update:modelValue="val => updateLangValue(lang, val)"
          :placeholder="`${fieldData.label}`"
          :inputName="`${fieldName}.${lang}.value`"
          :type="fieldData.type || 'text'"
          :copyButton="true"
          :isError="!!get(form.errors, `${fieldName}.${lang}.value`)"
        >
          <template #stateIcon>
            <div class="mr-2 h-full flex items-center pointer-events-none">
              <FontAwesomeIcon
                v-if="get(form.errors, `${fieldName}.${lang}.value`)"
                icon="fas fa-exclamation-circle"
                class="h-5 w-5 text-red-500"
              />
              <FontAwesomeIcon
                v-if="form.recentlySuccessful"
                icon="fas fa-check-circle"
                class="h-5 w-5 text-green-500"
              />
            </div>
          </template>
        </PureInput>
      </div>
      <p
        v-if="get(form.errors, `${fieldName}.${lang}.value`)"
        class="mt-2 text-sm text-red-600"
      >
        {{ get(form.errors, `${fieldName}.${lang}.value`) }}
      </p>
    </div>

    <!-- Button to open modal -->
    <button
      class="text-sm text-blue-600 underline hover:text-blue-800 transition"
      @click="showModal = true"
    >
      <FontAwesomeIcon :icon="faLanguage" /> Edit other languages
    </button>

    <!-- PrimeVue Dialog Modal -->
    <Dialog
      v-model:visible="showModal"
      modal
      header="Translate Other Languages"
      class="w-[500px]"
    >
      <div class="space-y-4">
        <div
          v-for="[lang, langData] in allLangs"
          :key="lang"
          class="relative"
        >
          <div class="flex w-full rounded-md shadow-sm overflow-hidden border border-gray-300 focus-within:ring-1 focus-within:ring-blue-500">
            <span
              class="inline-flex items-center px-3 bg-gray-100 border-r border-gray-300 text-gray-600 text-sm font-medium"
            >
              {{ lang }}
            </span>
            <PureInput
              class="flex-1 border-0 rounded-none focus:ring-0"
              :modelValue="form[fieldName][lang].value"
              @update:modelValue="val => updateLangValue(lang, val)"
              :placeholder="`${fieldData.label}`"
              :inputName="`${fieldName}.${lang}.value`"
              :type="fieldData.type || 'text'"
              :copyButton="true"
              :isError="!!get(form.errors, `${fieldName}.${lang}.value`)"
            >
              <template #stateIcon>
                <div class="mr-2 h-full flex items-center pointer-events-none">
                  <FontAwesomeIcon
                    v-if="get(form.errors, `${fieldName}.${lang}.value`)"
                    icon="fas fa-exclamation-circle"
                    class="h-5 w-5 text-red-500"
                  />
                  <FontAwesomeIcon
                    v-if="form.recentlySuccessful"
                    icon="fas fa-check-circle"
                    class="h-5 w-5 text-green-500"
                  />
                </div>
              </template>
            </PureInput>
          </div>
          <p
            v-if="get(form.errors, `${fieldName}.${lang}.value`)"
            class="mt-2 text-sm text-red-600"
          >
            {{ get(form.errors, `${fieldName}.${lang}.value`) }}
          </p>
        </div>
      </div>
    </Dialog>
  </div>
</template>

