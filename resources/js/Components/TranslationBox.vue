<script setup lang="ts">
import { ref, watch, inject } from 'vue'
import { router } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import { notify } from '@kyvg/vue3-notification'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faLanguage } from '@fas'

import PureInput from '@/Components/Pure/PureInput.vue'
import PureTextarea from '@/Components/Pure/PureTextarea.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { routeType } from '@/types/route'
import SideEditorInputHTML from './CMS/Fields/SideEditorInputHTML.vue'

import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'

const props = withDefaults(defineProps<{
  master: {
    name: string
    description: string
    description_title: string
    description_extra: string
  }
  title: string
  needTranslation: Object
  save_route: routeType
  languages?: {
    code: string
    name: string
  }[]
}>(), {
  title: 'Multi-language Translations',
  
})


// Language options
const locale = inject('locale', aikuLocaleStructure)
const langOptions = props.languages ?   Object.values(props.languages) : Object.values(locale.languageOptions)

// Retrieve from localStorage, or default to first language
const storedLang = localStorage.getItem('translation_box')
const initialLang = storedLang || langOptions[0]?.code || 'en'
const selectedLangCode = ref(initialLang)

// Validate initialLang
if (!langOptions.some(l => l.code === selectedLangCode.value)) {
  selectedLangCode.value = langOptions[0]?.code || 'en'
}

// Dynamic translations per language
const translations = ref(
  langOptions.reduce((acc, lang) => {
    const code = lang.code
    acc[code] = {
      name: props.needTranslation.name_i8n?.[code] || '',
      description: props.needTranslation.description_i8n?.[code] || '',
      description_title: props.needTranslation.description_title_i8n?.[code] || '',
      description_extra: props.needTranslation.description_extra_i8n?.[code] || ''
    }
    return acc
  }, {} as Record<string, {
    name: string
    description: string
    description_title: string
    description_extra: string
  }>)
)

// Controlled fields
const translationTitle = ref('')
const translationDescription = ref('')
const translationDescTitle = ref('')
const translationDescExtra = ref('')

// Update fields on language switch
const updateTranslationFields = () => {
  const current = translations.value[selectedLangCode.value]
  translationTitle.value = current?.name || ''
  translationDescription.value = current?.description || ''
  translationDescTitle.value = current?.description_title || ''
  translationDescExtra.value = current?.description_extra || ''
}

// Watch language change — save to localStorage & update fields
watch(selectedLangCode, (newLang) => {
  localStorage.setItem('translation_box', newLang)
  updateTranslationFields()
}, { immediate: true })

// Sync fields to translations
watch(
  [translationTitle, translationDescription, translationDescTitle, translationDescExtra],
  () => {
    translations.value[selectedLangCode.value] = {
      name: translationTitle.value,
      description: translationDescription.value,
      description_title: translationDescTitle.value,
      description_extra: translationDescExtra.value
    }
  }
)

const isLoading = ref(false)

const saveTranslation = () => {
  const master = {
    name: props.master.name,
    description: props.master.description,
    description_title: props.master.description_title,
    description_extra: props.master.description_extra
  }

  router.patch(
    route(props.save_route.name, props.save_route.parameters),
    { translations: translations.value, master },
    {
      preserveScroll: true,
      onStart: () => (isLoading.value = true),
      onSuccess: () => {
        notify({ title: trans('Success'), text: trans('Success to save translation'), type: 'success' })
      },
      onError: () => {
        notify({ title: trans('Something went wrong'), text: trans('Failed to save translation'), type: 'error' })
      },
      onFinish: () => (isLoading.value = false)
    }
  )
}
</script>


<template>

  <div class="px-4 grid grid-cols-2 gap-3 mt-4">
    <h2 v-if="props.title" class="text-lg font-bold flex items-center gap-2">
      <FontAwesomeIcon :icon="faLanguage" />
      {{ props.title }}
    </h2>
    <!-- Right: Translation Panel -->
    <div class="col-span-2">
      <div class="bg-white border rounded-lg shadow-sm p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="col-span-2">
            <div class="flex flex-wrap gap-2">
              <Button v-for="opt in langOptions" @click="selectedLangCode = opt.code" :key="selectedLangCode + opt.code"
                :label="opt.name" size="xxs" :type="selectedLangCode === opt.code ? 'primary' : 'tertiary'" />
            </div>
          </div>

          <!-- Master Language Form -->
          <div class="bg-gray-50 border border-gray-300 rounded-md p-4 shadow-sm">
            <h3 class="text-base font-semibold mb-3">{{ trans('Master') }}</h3>

            <div class="space-y-3">
              <div>
                <label class="block text-xs text-gray-700 mb-1">{{ trans('Title') }}</label>
                <PureInput v-model="props.master.name" placeholder="Enter title" class="text-sm" />
              </div>
              <div>
                <label class="block text-xs text-gray-700 mb-1">{{ trans('Description Title') }}</label>
                <PureInput v-model="props.master.description_title" placeholder="Enter description title"
                  class="text-sm" />
              </div>
              <div>
                <label class="block text-xs text-gray-700 mb-1">{{ trans('Description') }}</label>
                <SideEditorInputHTML v-model="props.master.description" rows="3" class="text-sm" />
              </div>
              <div>
                <label class="block text-xs text-gray-700 mb-1">{{ trans('Description Extra') }}</label>
                <SideEditorInputHTML v-model="props.master.description_extra" rows="3" class="text-sm" />
              </div>
            </div>
          </div>

          <!-- Translated Language Form -->
          <div class="bg-white border border-gray-300 rounded-md p-4 shadow-sm">
            <h3 class="text-base font-semibold mb-3">
              {{ trans('Translation') }} ({{ selectedLangCode?.toUpperCase() || '—' }})
            </h3>

            <div class="space-y-3">
              <div>
                <label class="block text-xs text-gray-700 mb-1">{{ trans('Title') }}</label>
                <PureInput v-model="translationTitle" placeholder="Enter translated title" class="text-sm" />
              </div>
              <div>
                <label class="block text-xs text-gray-700 mb-1">{{ trans('Description Title') }}</label>
                <PureInput v-model="translationDescTitle" placeholder="Enter translated description title"
                  class="text-sm" />
              </div>
              <div>
                <label class="block text-xs text-gray-700 mb-1">{{ trans('Description') }}</label>
                <SideEditorInputHTML v-model="translationDescription" rows="3" class="text-sm" />
              </div>
              <div>
                <label class="block text-xs text-gray-700 mb-1">{{ trans('Description Extra') }}</label>
                <SideEditorInputHTML v-model="translationDescExtra" rows="3" class="text-sm" />
              </div>
            </div>
          </div>
        </div>

        <!-- Save Button -->
        <div class="flex justify-end mt-6">
          <Button type="save" @click="saveTranslation" :loading="isLoading" />
        </div>
      </div>
    </div>
  </div>
</template>

