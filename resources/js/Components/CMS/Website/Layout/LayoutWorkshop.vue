<script setup lang="ts">
import { useColorTheme } from '@/Composables/useStockList'
import ColorSchemeWorkshopWebsite from '@/Components/CMS/Website/Layout/ColorSchemeWorkshopWebsite.vue'
import { routeType } from '@/types/route'
import Button from '@/Components/Elements/Buttons/Button.vue'
import SideEditor from '@/Components/Workshop/SideEditor/SideEditor.vue'
import { onMounted, ref, toRaw } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faPaintBrushAlt, faRocketLaunch, faChevronDown } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { isEqual } from 'lodash-es'
import { trans } from 'laravel-vue-i18n'
import Blueprint from './Blueprint'

library.add(faPaintBrushAlt, faRocketLaunch, faChevronDown)

interface LayoutTheme {
  color: string[]
  layout: string
  fontFamily: string
}

interface RouteList {
  headerRoute: routeType
  footerRoute: routeType
  webpageRoute: routeType
  notificationRoute: routeType
  menuLeftRoute: routeType
  menuRightRoute: routeType
  menuRoute: routeType
}

const props = defineProps<{
  data: {
    routeList: RouteList
    updateColorRoute: routeType
    layout: LayoutTheme
  }
}>()

const emit = defineEmits<{
  'update:layout': [layout: LayoutTheme]
}>()

const LAYOUT_OPTIONS = [
  { value: 'fullscreen', label: 'Fullscreen', pattern: 'repeating-linear-gradient(45deg, #ebf8ff, #ebf8ff 10px, #bee3f8 10px, #bee3f8 20px)' },
  { value: 'blog', label: 'Middle', pattern: 'repeating-linear-gradient(45deg, #ebf8ff, #ebf8ff 10px, #bee3f8 10px, #bee3f8 20px)', width: 'w-[60%]' }
]

const colorThemes = [...useColorTheme]
const isLoadingPublish = ref(false)
const fieldGroupAnimateSection = ref<string[]>([])
const isColorDropdownOpen = ref(false)

const handleColorThemeSelect = (colorTheme: string[]) => {
  emit('update:layout', {
    ...toRaw(props.data.layout),
    color: [...colorTheme]
  })
}

const handleLayoutChange = (layout: string) => {
  emit('update:layout', {
    ...toRaw(props.data.layout),
    layout
  })
}

/* const handlePublishLayout = () => {
  emit('publish:layout', props.data.layout)
} */

onMounted(() => {
  if (!props.data.layout?.color) {
    emit('update:layout', [...colorThemes[0]])
  }

  if (route().v().query?.section) {
    setTimeout(() => {
      fieldGroupAnimateSection.value = ['bg-yellow-500/80']
      setTimeout(() => {
        fieldGroupAnimateSection.value = []
      }, 800)
    }, 130)
  }
})
</script>

<template>
  <div class="p-8 grid grid-cols-[320px_1fr] gap-6 bg-gray-50 rounded-lg h-[79vh]">
    <!-- Left Panel: Settings -->
    <div class="flex flex-col justify-between bg-white rounded-lg shadow-md h-full overflow-hidden">
      <div class="overflow-y-auto p-4 space-y-6">
        <!-- Theme Colors Section -->
        <div id="theme_colors" class="rounded pb-4 transition-all duration-1000" :class="fieldGroupAnimateSection">
          <div class="flex items-center gap-2 mb-4">
            <hr class="h-0.5 rounded-full w-full bg-gray-300" />
            <span class="whitespace-nowrap text-sm text-gray-600 font-semibold">{{ trans('Select Theme') }}</span>
            <hr class="h-0.5 rounded-full w-full bg-gray-300" />
          </div>

          <div class="relative">
            <button
              @click="isColorDropdownOpen = !isColorDropdownOpen"
              class="w-full flex items-center justify-between gap-2 p-3 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition"
            >
              <div class="flex items-center gap-2">
                <div class="flex ring-1 ring-gray-300 rounded-md overflow-hidden">
                  <div
                    v-for="(color, i) in data.layout?.color"
                    :key="i"
                    class="h-5 w-5"
                    :style="{ backgroundColor: color }"
                  />
                </div>
                <span class="text-sm text-gray-700">{{ trans('Theme') }} {{ colorThemes.findIndex(t => isEqual(t, data.layout?.color)) + 1 }}</span>
              </div>
              <FontAwesomeIcon
                icon="fal fa-chevron-down"
                class="text-gray-400 transition"
                :class="{ 'rotate-180': isColorDropdownOpen }"
                fixed-width
              />
            </button>

            <Transition name="fade">
              <div
                v-if="isColorDropdownOpen"
                class="absolute top-full left-0 right-0 mt-2 bg-white border border-gray-300 rounded-md shadow-lg z-10"
              >
                <div class="max-h-48 overflow-y-auto p-2 space-y-2">
                  <button
                    v-for="(colorTheme, index) in colorThemes"
                    :key="index"
                    @click="() => { handleColorThemeSelect(colorTheme); isColorDropdownOpen = false }"
                    class="w-full flex items-center justify-between gap-3 p-3 rounded-md hover:bg-gray-50 transition border-2 border-transparent"
                    :class="{ 'bg-indigo-50 border-indigo-500': isEqual(data.layout?.color, colorTheme) }"
                  >
                    <div class="flex items-center gap-3">
                      <div class="flex ring-1 ring-gray-300 rounded-md overflow-hidden">
                        <div
                          v-for="(color, i) in colorTheme"
                          :key="i"
                          class="h-5 w-5"
                          :style="{ backgroundColor: color }"
                        />
                      </div>
                      <span class="text-sm font-medium text-gray-700">{{ trans('Theme') }} {{ index + 1 }}</span>
                    </div>
                    <FontAwesomeIcon
                      v-if="isEqual(data.layout?.color, colorTheme)"
                      icon="fal fa-check"
                      class="text-green-600"
                      fixed-width
                    />
                  </button>
                </div>
              </div>
            </Transition>
          </div>
        </div>

        <!-- Layout Selector Section -->
        <div>
          <div class="flex items-center gap-2 mb-4">
            <hr class="h-0.5 rounded-full w-full bg-gray-300" />
            <span class="whitespace-nowrap text-sm text-gray-600 font-semibold">{{ trans('Select Layout') }}</span>
            <hr class="h-0.5 rounded-full w-full bg-gray-300" />
          </div>

          <div class="flex gap-4 justify-center flex-wrap">
            <label
              v-for="layout in LAYOUT_OPTIONS"
              :key="layout.value"
              :for="`radio-layout-${layout.value}`"
              class="flex flex-col items-center gap-2 p-4 border border-gray-200 rounded-md cursor-pointer hover:bg-gray-50 transition"
              :class="{ 'border-indigo-500 bg-indigo-50': data.layout?.layout === layout.value }"
            >
              <input
                :id="`radio-layout-${layout.value}`"
                name="radio-layout"
                type="radio"
                :value="layout.value"
                @change="handleLayoutChange(layout.value)"
                class="hidden"
              />
              <div class="w-20 h-12 bg-gray-200 rounded-md flex items-center justify-center overflow-hidden">
                <div
                  :class="layout.width || 'w-full'"
                  class="h-full rounded"
                  :style="{ background: layout.pattern }"
                />
              </div>
              <span class="text-sm font-semibold">{{ trans(layout.label) }}</span>
            </label>
          </div>
        </div>

        <!-- Font & Side Editor -->
        <SideEditor
          v-model="data.layout"
          :blueprint="Blueprint.blueprint"
          @update:modelValue="(e) => emit('update:layout', e)"
          :uploadImageRoute="null"
        />
      </div>
    </div>

    <!-- Right Panel: Preview -->
    <div class="rounded-lg shadow-md bg-white p-8 h-full overflow-y-auto">
      <ColorSchemeWorkshopWebsite
        :routeList="data.routeList"
        :theme="data.layout"
      />
    </div>
  </div>
</template>
