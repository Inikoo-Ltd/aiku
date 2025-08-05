<script setup lang="ts">
import { ref, watch, inject, onMounted, nextTick } from 'vue'
import TabView from 'primevue/tabview'
import TabPanel from 'primevue/tabpanel'
import PureInput from '../Pure/PureInput.vue'
import PureTextarea from '../Pure/PureTextarea.vue'
import Button from '../Elements/Buttons/Button.vue'
import EditTradeUnit from './EditTradeUnit.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faLanguage, faChevronLeft, faChevronRight } from '@fortawesome/free-solid-svg-icons'
import { trans } from "laravel-vue-i18n"
import { notify } from '@kyvg/vue3-notification'
import { router } from '@inertiajs/vue3'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import type { TradeUnit } from '@/types/trade-unit'
import type { routeType } from '@/types/route'

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

const locale = inject('locale', aikuLocaleStructure)
const langOptions = Object.values(locale.languageOptions)

const selectedLangCode = ref(langOptions[0]?.code || 'en')
const activeTabIndex = ref(0)

watch(activeTabIndex, (index) => {
  selectedLangCode.value = langOptions[index].code
})

const translatedTitle = ref('')
const translatedDescription = ref('')
const translatedDescriptionTitle = ref('')
const translatedDescriptionExtra = ref('')

const needToTranslate = ref(
  langOptions.reduce((acc, lang) => {
    acc[lang.code] = {
      name: props.data.tradeUnit.name_i8n?.[lang.code] || '',
      description: props.data.tradeUnit.description_i8n?.[lang.code] || '',
      description_title: props.data.tradeUnit.description_title_i8n?.[lang.code] || '',
      description_extra: props.data.tradeUnit.description_extra_i8n?.[lang.code] || ''
    }
    return acc
  }, {} as Record<string, {
    name: string
    description: string
    description_title: string
    description_extra: string
  }>)
)

watch(selectedLangCode, () => {
  const current = needToTranslate.value[selectedLangCode.value]
  translatedTitle.value = current.name
  translatedDescription.value = current.description
  translatedDescriptionTitle.value = current.description_title
  translatedDescriptionExtra.value = current.description_extra
}, { immediate: true })

watch([translatedTitle, translatedDescription, translatedDescriptionTitle, translatedDescriptionExtra], () => {
  needToTranslate.value[selectedLangCode.value] = {
    name: translatedTitle.value,
    description: translatedDescription.value,
    description_title: translatedDescriptionTitle.value,
    description_extra: translatedDescriptionExtra.value
  }
})

const isLoading = ref(false)

const saveTranslation = () => {
  router.patch(
    route('grp.models.trade-unit.translations.update', { tradeUnit: props.data.tradeUnit.id }),
    {
      translations: needToTranslate.value,
      master: {
        name: props.data.tradeUnit.name,
        description: props.data.tradeUnit.description,
        description_title: props.data.tradeUnit.description_title,
        description_extra: props.data.tradeUnit.description_extra
      }
    },
    {
      preserveScroll: true,
      onStart: () => isLoading.value = true,
      onFinish: () => isLoading.value = false,
      onSuccess: () => notify({ title: trans('Success'), text: trans('Success to save translation'), type: 'success' }),
      onError: () => notify({ title: trans('Error'), text: trans('Failed to save translation'), type: 'error' })
    }
  )
}

// Handle scrolling
const tabContainerRef = ref<HTMLElement | null>(null)
const scrollTabs = (direction: 'left' | 'right') => {
    console.log('scrollTabs', tabContainerRef.value?.querySelector('.p-tabview-tablist-scroll-container') )
  const nav = tabContainerRef.value?.querySelector('.p-tabview-tablist-scroll-container') as HTMLElement
  if (nav) {
    nav.scrollBy({ left: direction === 'left' ? -150 : 150, behavior: 'smooth' })
  }
}

onMounted(() => {
  nextTick(() => {
    const nav = tabContainerRef.value?.querySelector('.p-tabview-tablist-scroll-container') as HTMLElement
    if (nav) {
      nav.classList.add('flex', 'overflow-x-auto', 'no-scrollbar', 'whitespace-nowrap', 'gap-2')
    }
  })
})
</script>

<template>
  <div class="px-8 grid grid-cols-2 gap-8">
    <!-- Edit Component -->
    <EditTradeUnit
      :tags_selected_id="props.data.tags_selected_id"
      :brand="props.data.brand"
      :brand_routes="props.data.brand_routes"
      :tags="props.data.tags"
      :tag_routes="props.data.tag_routes"
    />

    <!-- Translation Section -->
    <div class="col-span-2 mt-6">
      <div class="bg-white border border-gray-300 rounded-lg shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-bold flex items-center gap-2">
            <FontAwesomeIcon :icon="faLanguage" />
            {{ trans('Multi-language Translations') }}
          </h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Master Language -->
          <div class="bg-gray-50 border border-gray-300 rounded-md p-4 shadow-sm">
            <h3 class="text-base font-semibold mb-3">{{ trans('Master (EN)') }}</h3>

            <div class="mb-3">
              <label class="block text-xs text-gray-700 mb-1">Title</label>
              <PureInput v-model="props.data.tradeUnit.name" type="text" class="text-sm" />
            </div>
            <div class="mb-3">
              <label class="block text-xs text-gray-700 mb-1">Description Title</label>
              <PureInput v-model="props.data.tradeUnit.description_title" type="text" class="text-sm" />
            </div>
            <div class="mb-3">
              <label class="block text-xs text-gray-700 mb-1">Description</label>
              <PureTextarea v-model="props.data.tradeUnit.description" rows="3" class="text-sm" />
            </div>
            <div>
              <label class="block text-xs text-gray-700 mb-1">Description Extra</label>
              <PureTextarea v-model="props.data.tradeUnit.description_extra" rows="3" class="text-sm" />
            </div>
          </div>

          <!-- Translations Tab -->
          <div class="relative tab-custom" ref="tabContainerRef">
            <!-- Scroll buttons -->
            <button @click="scrollTabs('left')" class="absolute left-0 top-5 z-10 bg-white shadow rounded-full p-1">
              <FontAwesomeIcon :icon="faChevronLeft" />
            </button>
            <button @click="scrollTabs('right')" class="absolute right-0 top-5 z-10 bg-white shadow rounded-full p-1">
              <FontAwesomeIcon :icon="faChevronRight" />
            </button>

            <TabView v-model:activeIndex="activeTabIndex" class="bg-gray-50 border border-gray-300 rounded-md p-4 shadow-sm">
              <TabPanel v-for="lang in langOptions" :key="lang.code" :style="{background : 'red'}">
                <template #header>
                  <span class="text-xs whitespace-nowrap">{{ lang.name }}</span>
                </template>

                <h3 class="text-base font-semibold mb-3">
                  {{ trans('Translation') }} {{ lang.name }} ({{ lang.code.toUpperCase()  }})
                </h3>

                <div class="mb-3">
                  <label class="block text-xs text-gray-700 mb-1">Title</label>
                  <PureInput v-model="translatedTitle" type="text" class="text-sm" />
                </div>
                <div class="mb-3">
                  <label class="block text-xs text-gray-700 mb-1">Description Title</label>
                  <PureInput v-model="translatedDescriptionTitle" type="text" class="text-sm" />
                </div>
                <div class="mb-3">
                  <label class="block text-xs text-gray-700 mb-1">Description</label>
                  <PureTextarea v-model="translatedDescription" rows="3" class="text-sm" />
                </div>
                <div>
                  <label class="block text-xs text-gray-700 mb-1">Description Extra</label>
                  <PureTextarea v-model="translatedDescriptionExtra" rows="3" class="text-sm" />
                </div>
              </TabPanel>
            </TabView>
          </div>
        </div>

        <!-- Save Button -->
        <div class="flex justify-end mt-6">
          <Button :type="'save'" @click="saveTranslation" :loading="isLoading" />
        </div>
      </div>
    </div>
  </div>
</template>


<style>
.no-scrollbar::-webkit-scrollbar {
  display: none;
}
.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}

/* Scrollable tab headers */
.tab-custom .p-tabview-nav-container {
  position: relative;
  overflow-x: auto;
  scrollbar-width: none;
}
.tab-custom .p-tabview-nav-container::-webkit-scrollbar {
  display: none;
}

/* Tab content area */
.tab-custom .p-tabview-panels {
  background-color: #F9FAFB !important; /* bg-gray-50 */
  border-radius: 0rem;
  padding: 0rem;
  box-shadow: 0 0px 0px 0 rgb(0 0 0 / 0.05);
}
.tab-custom .p-tabview-panel {
  background-color: #F9FAFB !important;
  border: 0px solid #D1D5DB !important; /* border-gray-300 */
  border-radius: 0rem;
  padding: 1rem;
}
</style>

