<script setup lang="ts">
import { ref, watch, inject } from 'vue'
import { router } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import { notify } from '@kyvg/vue3-notification'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faLanguage } from '@fas'

import EditTradeUnit from './EditTradeUnit.vue'
import PureInput from '../Pure/PureInput.vue'
import PureTextarea from '../Pure/PureTextarea.vue'
import Button from '../Elements/Buttons/Button.vue'

import { routeType } from '@/types/route'
import { TradeUnit } from '@/types/trade-unit'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'

const props = defineProps<{
  data: {
    tradeUnit: TradeUnit
    brand: {}
    brand_routes: Record<string, routeType>
    tag_routes: Record<string, routeType>
    tags: {}[]
    tags_selected_id: number[]
  }
}>()

// Language options
const locale = inject('locale', aikuLocaleStructure)
const langOptions = Object.values(locale.languageOptions)
const selectedLangCode = ref(langOptions[0]?.code || 'en')

// Dynamic translations per language
const translations = ref(
  langOptions.reduce((acc, lang) => {
    const code = lang.code
    acc[code] = {
      name: props.data.tradeUnit.name_i8n?.[code] || '',
      description: props.data.tradeUnit.description_i8n?.[code] || '',
      description_title: props.data.tradeUnit.description_title_i8n?.[code] || '',
      description_extra: props.data.tradeUnit.description_extra_i8n?.[code] || ''
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

watch(selectedLangCode, updateTranslationFields, { immediate: true })

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
    name: props.data.tradeUnit.name,
    description: props.data.tradeUnit.description,
    description_title: props.data.tradeUnit.description_title,
    description_extra: props.data.tradeUnit.description_extra
  }

  router.patch(
    route('grp.models.trade-unit.translations.update', { tradeUnit: props.data.tradeUnit.id }),
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
  <div class="px-8 grid grid-cols-2 gap-8">
    <!-- Left: Master Content -->
    <EditTradeUnit
      :tags_selected_id="props.data.tags_selected_id"
      :brand="props.data.brand"
      :brand_routes="props.data.brand_routes"
      :tags="props.data.tags"
      :tag_routes="props.data.tag_routes"
    />

    <!-- Right: Translation Panel -->
    <div class="col-span-2 mt-6">
      <div class="bg-white border rounded-lg shadow-sm p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Header & Language Selector -->
          <div>
            <h2 class="text-lg font-bold flex items-center gap-2">
              <FontAwesomeIcon :icon="faLanguage" />
              {{ trans('Multi-language Translations') }}
            </h2>
          </div>

          <div>
            <div class="flex flex-wrap gap-2">
              <Button  v-for="opt in langOptions" @click="selectedLangCode = opt.code"
                :key="selectedLangCode + opt.code" :label="opt.name" size="xxs"  :type="selectedLangCode === opt.code ? 'primary' : 'tertiary'"/>
            </div>
          </div>

          <!-- Master Language Form -->
          <div class="bg-gray-50 border border-gray-300 rounded-md p-4 shadow-sm">
            <h3 class="text-base font-semibold mb-3">{{ trans('Master') }}</h3>

            <div class="space-y-3">
              <div>
                <label class="block text-xs text-gray-700 mb-1">{{trans('Title')}}</label>
                <PureInput v-model="props.data.tradeUnit.name" placeholder="Enter title" class="text-sm" />
              </div>
              <div>
                <label class="block text-xs text-gray-700 mb-1">{{trans('Description Title')}}</label>
                <PureInput v-model="props.data.tradeUnit.description_title" placeholder="Enter description title" class="text-sm" />
              </div>
              <div>
                <label class="block text-xs text-gray-700 mb-1">{{trans('Description')}}</label>
                <PureTextarea v-model="props.data.tradeUnit.description" rows="3" class="text-sm" />
              </div>
              <div>
                <label class="block text-xs text-gray-700 mb-1">{{trans('Description Extra')}}</label>
                <PureTextarea v-model="props.data.tradeUnit.description_extra" rows="3" class="text-sm" />
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
                <label class="block text-xs text-gray-700 mb-1">{{trans('Title')}}</label>
                <PureInput v-model="translationTitle" placeholder="Enter translated title" class="text-sm" />
              </div>
              <div>
                <label class="block text-xs text-gray-700 mb-1">{{trans('Description Title')}}</label>
                <PureInput v-model="translationDescTitle" placeholder="Enter translated description title" class="text-sm" />
              </div>
              <div>
                <label class="block text-xs text-gray-700 mb-1">{{trans('Description')}}</label>
                <PureTextarea v-model="translationDescription" rows="3" class="text-sm" />
              </div>
              <div>
                <label class="block text-xs text-gray-700 mb-1">{{trans('Description Extra')}}</label>
                <PureTextarea v-model="translationDescExtra" rows="3" class="text-sm" />
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
