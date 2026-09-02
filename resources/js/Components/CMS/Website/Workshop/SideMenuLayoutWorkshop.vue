<script setup lang="ts">
import { onMounted, ref, toRaw } from 'vue'
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faPaintBrushAlt, faRocketLaunch, faChevronDown } from '@fal'
import { isEqual } from 'lodash-es'
import { trans } from 'laravel-vue-i18n'

import { useColorTheme } from '@/Composables/useStockList'
import SideEditor from '@/Components/Workshop/SideEditor/SideEditor.vue'
import Blueprint from '@/Components/CMS/Website/Layout/Blueprint'

library.add(faPaintBrushAlt, faRocketLaunch, faChevronDown)

interface LayoutTheme {
  color: string[]
  layout: string
  fontFamily: string
}

const props = defineProps<{
  layout: LayoutTheme
}>()

const emit = defineEmits<{
  'update:layout': [layout: LayoutTheme]
}>()

const LAYOUT_OPTIONS = [
  { value: 'fullscreen', label: 'Fullscreen', pattern: 'repeating-linear-gradient(45deg, #ebf8ff, #ebf8ff 10px, #bee3f8 10px, #bee3f8 20px)' },
  { value: 'blog', label: 'Middle', pattern: 'repeating-linear-gradient(45deg, #ebf8ff, #ebf8ff 10px, #bee3f8 10px, #bee3f8 20px)', width: 'w-[60%]' }
]

const colorThemes = [...useColorTheme]
const fieldGroupAnimateSection = ref<string[]>([])
const isColorDropdownOpen = ref(false)

const handleColorThemeSelect = (colorTheme: string[]) => {
  emit('update:layout', {
    ...toRaw(props.layout),
    color: [...colorTheme]
  })
}

const handleLayoutChange = (layout: string) => {
  emit('update:layout', {
    ...toRaw(props.layout),
    layout
  })
}

onMounted(() => {
  if (!props.layout?.color) {
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
  <div class="overflow-y-auto p-3 space-y-4">
    <div id="theme_colors" class="rounded transition-all duration-1000" :class="fieldGroupAnimateSection">
      <p class="mb-2 text-xxs font-semibold uppercase tracking-wide text-gray-400">{{ trans('Select Theme') }}</p>

      <div class="relative">
        <button
          @click="isColorDropdownOpen = !isColorDropdownOpen"
          class="w-full flex items-center justify-between gap-2 px-2.5 py-2 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition"
        >
          <div class="flex items-center gap-2">
            <div class="flex ring-1 ring-gray-300 rounded-md overflow-hidden">
              <div
                v-for="(color, i) in layout?.color"
                :key="i"
                class="h-4 w-4"
                :style="{ backgroundColor: color }"
              />
            </div>
            <span class="text-xs text-gray-700">{{ trans('Theme') }} {{ colorThemes.findIndex(t => isEqual(t, layout?.color)) + 1 }}</span>
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
            class="absolute top-full left-0 right-0 mt-1.5 bg-white border border-gray-300 rounded-md shadow-lg z-10"
          >
            <div class="max-h-52 overflow-y-auto p-1.5 space-y-1">
              <button
                v-for="(colorTheme, index) in colorThemes"
                :key="index"
                @click="() => { handleColorThemeSelect(colorTheme); isColorDropdownOpen = false }"
                class="w-full flex items-center justify-between gap-2 px-2 py-1.5 rounded-md hover:bg-gray-50 transition border border-transparent"
                :class="{ 'bg-indigo-50 border-indigo-500': isEqual(layout?.color, colorTheme) }"
              >
                <div class="flex items-center gap-2">
                  <div class="flex ring-1 ring-gray-300 rounded-md overflow-hidden">
                    <div
                      v-for="(color, i) in colorTheme"
                      :key="i"
                      class="h-4 w-4"
                      :style="{ backgroundColor: color }"
                    />
                  </div>
                  <span class="text-xs font-medium text-gray-700">{{ trans('Theme') }} {{ index + 1 }}</span>
                </div>
                <FontAwesomeIcon
                  v-if="isEqual(layout?.color, colorTheme)"
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

    <div>
      <p class="mb-2 text-xxs font-semibold uppercase tracking-wide text-gray-400">{{ trans('Select Layout') }}</p>

      <div class="grid grid-cols-2 gap-2">
        <label
          v-for="layoutOption in LAYOUT_OPTIONS"
          :key="layoutOption.value"
          :for="`radio-layout-${layoutOption.value}`"
          class="flex flex-col items-center gap-1.5 p-2 border border-gray-200 rounded-md cursor-pointer hover:bg-gray-50 transition"
          :class="{ 'border-indigo-500 bg-indigo-50': layout?.layout === layoutOption.value }"
        >
          <input
            :id="`radio-layout-${layoutOption.value}`"
            name="radio-layout"
            type="radio"
            :value="layoutOption.value"
            @change="handleLayoutChange(layoutOption.value)"
            class="hidden"
          />
          <div class="w-full h-10 bg-gray-200 rounded flex items-center justify-center overflow-hidden">
            <div
              :class="layoutOption.width || 'w-full'"
              class="h-full rounded"
              :style="{ background: layoutOption.pattern }"
            />
          </div>
          <span class="text-xs font-medium">{{ trans(layoutOption.label) }}</span>
        </label>
      </div>
    </div>

    <SideEditor
      :modelValue="layout"
      :blueprint="Blueprint.blueprint"
      @update:modelValue="(e) => emit('update:layout', e)"
      :uploadImageRoute="null"
    />
  </div>
</template>
