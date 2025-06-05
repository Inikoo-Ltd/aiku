<script setup lang="ts">
import { useColorTheme } from '@/Composables/useStockList'
import ColorSchemeWorkshopWebsite from '@/Components/CMS/Website/Layout/ColorSchemeWorkshopWebsite.vue'
import { routeType } from '@/types/route'
import { router } from '@inertiajs/vue3'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { useFontFamilyList } from '@/Composables/useFont'
import SideEditor from '@/Components/Workshop/SideEditor/SideEditor.vue'

import { onMounted, ref } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faPaintBrushAlt, faRocketLaunch } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { get, isEqual, set } from 'lodash-es'
import { trans } from 'laravel-vue-i18n'
import { notify } from '@kyvg/vue3-notification'
import Blueprint from './Blueprint'

library.add(faPaintBrushAlt, faRocketLaunch)

const props = defineProps<{
  modelValue: any
  data: {
    routeList: {
      headerRoute: routeType
      footerRoute: routeType
      webpageRoute: routeType
      notificationRoute: routeType
      menuLeftRoute: routeType
      menuRightRoute: routeType
      menuRoute: routeType
    }
    updateColorRoute: routeType
    theme: {
      color: string[]
      layout: string
      fontFamily: string
    }
  }
}>()

const listColorTheme = [...useColorTheme]
const onClickColor = (colorTheme: string[], index: number) => {
  set(props.data, 'theme.color', colorTheme)
}
const isLoadingPublish = ref(false)

const fieldGroupAnimateSection = ref()
onMounted(() => {
  if (!get(props.data, 'theme.color', false)) {
    set(props.data, 'theme.color', [...listColorTheme[0]])
  }

  route().v().query?.section &&
    setTimeout(() => {
      fieldGroupAnimateSection.value = ['bg-yellow-500/80']
      setTimeout(() => {
        fieldGroupAnimateSection.value = []
      }, 800)
    }, 130)
})

const onPublishTheme = () => {
  router.patch(
    route(props.data.updateColorRoute.name, props.data.updateColorRoute.parameters),
    { layout: props.data.theme },
    {
      onStart: () => (isLoadingPublish.value = true),
      onSuccess: () => {
        notify({
          title: trans('Success!'),
          text: trans('Theme config changed'),
          type: 'success'
        })
      },
      onError: () => {
        notify({
          title: trans('Something went wrong'),
          text: trans('Unsuccessfully change theme config'),
          type: 'error'
        })
      },
      onFinish: () => (isLoadingPublish.value = false)
    }
  )
}
</script>

<template>
  <div class="p-8 grid grid-cols-[320px_1fr] gap-6 bg-gray-50 rounded-lg h-[79vh]">
    <!-- Sidebar -->
    <div class="flex flex-col justify-between bg-white rounded-lg shadow-md h-full overflow-hidden">
      <!-- Scrollable Content -->
      <div class="overflow-y-auto p-4 space-y-6">
        <!-- Theme Colors -->
        <div id="theme_colors" class="rounded pb-4 transition-all duration-1000" :class="fieldGroupAnimateSection">
          <div class="flex items-center gap-2 mb-4">
            <hr class="h-0.5 rounded-full w-full bg-gray-300" />
            <span class="whitespace-nowrap text-sm text-gray-600 font-semibold">{{ trans("Select Theme") }}</span>
            <hr class="h-0.5 rounded-full w-full bg-gray-300" />
          </div>

          <div class="flex flex-wrap justify-center gap-8">
            <div v-for="(colorTheme, index) in listColorTheme" :key="index" class="relative flex items-center gap-x-1">
              <div
                @click="onClickColor(colorTheme, index)"
                class="flex ring-1 ring-gray-300 transition duration-300 rounded-md overflow-hidden cursor-pointer"
                :class="{ 'ring-2 ring-indigo-500': isEqual(data.theme?.color, colorTheme) }"
              >
                <div class="h-6 w-6" v-for="(color, i) in colorTheme" :key="i" :style="{ backgroundColor: color }"></div>
              </div>
              <Transition name="spin-to-down">
                <FontAwesomeIcon
                  v-if="isEqual(data.theme?.color, colorTheme)"
                  icon="fal fa-check"
                  class="absolute -right-6 text-green-600"
                  fixed-width
                  aria-hidden="true"
                />
              </Transition>
            </div>
          </div>
        </div>

        <!-- Layout Selector -->
        <div>
          <div class="flex items-center gap-2 mb-4">
            <hr class="h-0.5 rounded-full w-full bg-gray-300" />
            <span class="whitespace-nowrap text-sm text-gray-600 font-semibold">Select Layout</span>
            <hr class="h-0.5 rounded-full w-full bg-gray-300" />
          </div>

          <div class="flex gap-4 justify-center flex-wrap">
            <!-- Fullscreen -->
            <label
              for="radio-layout-fullscreen"
              class="flex flex-col items-center gap-2 p-4 border border-gray-200 rounded-md cursor-pointer hover:bg-gray-50 transition"
              :class="{ 'border-indigo-500 bg-indigo-50': data.theme?.layout === 'fullscreen' }"
            >
              <input
                id="radio-layout-fullscreen"
                name="radio-layout"
                type="radio"
                value="fullscreen"
                @change="(e) => set(data, 'theme.layout', e.target.value)"
                class="hidden"
              />
              <div class="w-20 h-12 bg-gray-200 rounded-md flex items-center justify-center">
                <div
                  class="w-full h-full"
                  style="background: repeating-linear-gradient(45deg, #ebf8ff, #ebf8ff 10px, #bee3f8 10px, #bee3f8 20px);"
                ></div>
              </div>
              <span class="text-sm font-semibold">{{ trans("Fullscreen") }}</span>
            </label>

            <!-- Blog -->
            <label
              for="radio-layout-blog"
              class="flex flex-col items-center gap-2 p-4 border border-gray-200 rounded-md cursor-pointer hover:bg-gray-50 transition"
              :class="{ 'border-indigo-500 bg-indigo-50': data.theme?.layout === 'blog' }"
            >
              <input
                id="radio-layout-blog"
                name="radio-layout"
                type="radio"
                value="blog"
                @change="(e) => set(data, 'theme.layout', e.target.value)"
                class="hidden"
              />
              <div class="w-20 h-12 bg-gray-200 rounded-md flex items-center justify-center">
                <div
                  class="w-[60%] h-full rounded"
                  style="background: repeating-linear-gradient(45deg, #ebf8ff, #ebf8ff 10px, #bee3f8 10px, #bee3f8 20px);"
                ></div>
              </div>
              <span class="text-sm font-semibold">{{ trans("Middle") }}</span>
            </label>
          </div>
        </div>

        <!-- Font & SideEditor -->
        <SideEditor
          v-model="data.theme"
          :blueprint="Blueprint.blueprint"
          @update:modelValue="(e) => (data.theme = e)"
          :uploadImageRoute="null"
        />
      </div>

      <!-- Fixed Bottom Button -->
      <div class="p-4 border-t bg-white">
        <Button
          @click="() => onPublishTheme()"
          type="submit"
          :loading="isLoadingPublish"
          full
          label="Publish"
          icon="fal fa-rocket-launch"
        />
      </div>
    </div>

    <!-- Preview Panel -->
    <div class="rounded-lg shadow-md bg-white p-8 h-full overflow-y-auto">
      <ColorSchemeWorkshopWebsite
        :routeList="data.routeList"
        :theme="data.theme"
      />
    </div>
  </div>
</template>
