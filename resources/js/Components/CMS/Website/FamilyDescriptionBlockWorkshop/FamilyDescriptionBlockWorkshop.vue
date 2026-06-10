<script setup lang="ts">
import { ref, provide, inject, computed, toRaw, onMounted } from "vue"
import { router } from "@inertiajs/vue3"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { notify } from "@kyvg/vue3-notification"
import Drawer from "primevue/drawer"
import axios from "axios"

import {
  faCube,
  faInfoCircle,
  faLink
} from "@fal"

import {
  faStar,
  faCircle,
  faChevronLeft,
  faChevronRight,
  faDesktop
} from "@fas"

import { getComponent } from "@/Composables/getWorkshopComponents"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { setColorStyleRootByEl } from "@/Composables/useApp"
import { trans } from "laravel-vue-i18n"
import SideMenuFamilyDescriptionBlockWorkshop from "@/Components/CMS/Website/FamilyDescriptionBlockWorkshop/SideMenuFamilyDescriptionBlockWorkshop.vue"
import ScreenView from "@/Components/ScreenView.vue"
import type { routeType } from "@/types/route"
import { cloneDeep } from "lodash-es"
import { faDotCircle } from "@far"

library.add(
  faCube,
  faLink,
  faStar,
  faCircle,
  faChevronLeft,
  faChevronRight,
  faDesktop
)

const props = defineProps<{
  data: {
    web_block_types: any
    autosaveRoute: routeType
    layout: any
    family: any[]
    update_family_route: routeType
  }
  layout_theme: Array<any>
}>()
const emits = defineEmits()
// STATE
const layoutState = ref(toRaw(props.data.layout))
const layoutTheme = inject("layout", layoutStructure)
const selectedBlock = ref<any>(null)
const rootRef = ref<HTMLElement | null>(null)
const visibleDrawer = ref(false)
const isLoadingSave = ref(false)
const loadingTemplate = ref(false)
const previewKey = ref<string | number>("")
const currentView = ref<"desktop" | "tablet" | "mobile">("desktop")
const themeColor4 = props.layout_theme?.color?.[4] || '#fcd34d'
provide("visibleDrawer", visibleDrawer)
provide("currentView", currentView)

// ✅ FIXED: keep stable structure
const dataPicked = ref<{
  sub_department: any | null
  families: any[]
  family: any | null
}>({
  sub_department: null,
  families: [],
  family: null
})

// VIEW SIZE
const iframeClass = computed(() => {
  switch (currentView.value) {
    case "mobile":
      return "w-[375px] h-[667px] mx-auto"
    case "tablet":
      return "w-[768px] h-[1024px] mx-auto"
    default:
      return "w-full h-full"
  }
})

// FETCH TEMPLATE
const onPickTemplate = async (template: any) => {
  loadingTemplate.value = true

  try {
    const response = await axios.get(
      route("grp.json.workshop.fetch_descriptions_layout", {
        website: route().params["website"],
        webBlockType: template.code
      })
    )

    if (response.data) {
      layoutState.value = response.data
      debouncedAutosave()
      emits("update:layout", response.data);
    }
  } catch (error) {
    console.error("Failed to fetch template", error)
  } finally {
    loadingTemplate.value = false
  }
}

// ✅ FIXED: do NOT replace object
const onChangeFamily = (payload: any) => {
  dataPicked.value.family = payload
  previewKey.value = payload?.code || payload?.id || Date.now()
  visibleDrawer.value = false
}

// AUTOSAVE
const autosave = () => {
  const payload = cloneDeep(layoutState.value)
  router.patch(
    route(
      props.data.autosaveRoute.name,
      props.data.autosaveRoute.parameters
    ),
    { layout: payload },
    {
      onStart: () => (isLoadingSave.value = true),
      onFinish: () => (isLoadingSave.value = false),
      onError: (errors) => {
        notify({
          title: "Autosave Failed",
          text: errors?.message ?? "Unknown error occurred",
          type: "error"
        })
      }
    }
  )
}

let autosaveTimer: number | null = null

const debouncedAutosave = () => {
  if (autosaveTimer) clearTimeout(autosaveTimer)
  autosaveTimer = window.setTimeout(autosave, 800)
}



onMounted(() => {
  if (rootRef.value && props.layout_theme?.color) {
    setColorStyleRootByEl(rootRef.value, props.layout_theme.color)
  }
  if (props?.data?.family?.data[0]) onChangeFamily(props.data.family.data[0])
})


</script>

<template>
  <div class="pt-4">

    <!-- LAYOUT -->
    <div class="h-[85vh] grid grid-cols-1 lg:grid-cols-12 gap-4 p-3">

      <!-- LEFT MENU -->
      <div
        class="col-span-1 lg:col-span-3 bg-[#F9FAFB] rounded-xl shadow-md p-3 lg:p-4 overflow-y-auto border max-h-[40vh] lg:max-h-full">
        <SideMenuFamilyDescriptionBlockWorkshop :data="layoutState" :webBlockTypes="props.data.web_block_types"
          :selectedBlock="selectedBlock" @update:data="layoutState = $event"
          @update:selectedBlock="selectedBlock = $event" @set-up-template="onPickTemplate"
          @auto-save="debouncedAutosave" />
      </div>

      <!-- PREVIEW -->
      <div class="col-span-1 lg:col-span-9 bg-white rounded-xl shadow-md flex flex-col border overflow-hidden">

        <!-- HEADER -->
        <div class="flex justify-between items-center px-3 lg:px-4 py-2 bg-gray-100 border-b shrink-0">
          <div class="py-1 px-2 hidden lg:block">
            <ScreenView v-model="currentView" />
          </div>

          <div class="text-xs lg:text-sm text-gray-600 italic cursor-pointer truncate" @click="visibleDrawer = true">
            <span v-if="dataPicked.family?.name">
              Preview: <strong>{{ dataPicked.family.name }}</strong>
            </span>
            <span v-else>Pick The Family</span>
          </div>
        </div>

        <!-- CONTENT -->
        <div class="flex-1 min-h-0">
          <div v-if="layoutState && dataPicked.family" :key="previewKey" ref="rootRef" :class="[
            ' h-full overflow-auto',
            iframeClass
          ]">
            <div v-for="(block, key) in layoutState" :key="key + '-' + previewKey"
              class="transition-all duration-200" :class="{
                'border-2 block-active': key === selectedBlock?.code,
                'border border-transparent': key !== selectedBlock?.code
              }">
              <component :is="getComponent(key)" :routeEditFamiliesOverview="props.data.update_family_route"
                :screenType="currentView" :modelValue="{
                  ...block?.fieldValue,
                  family: dataPicked.family
                }" />
            </div>
          </div>

          <!-- EMPTY STATE -->
          <div v-else class="flex items-center justify-center text-gray-500 h-full">
            <div class="flex flex-col items-center">
              <FontAwesomeIcon :icon="faInfoCircle" class="text-3xl lg:text-4xl mb-2" />
              <h3 class="text-sm lg:text-lg font-semibold">
                {{ trans("No Family selected") }}
              </h3>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <Drawer v-model:visible="visibleDrawer" position="right" :pt="{ root: { style: 'width: 30vw' } }">
    <template #header>
      <div>
        <h2 class="text-base font-semibold">{{ trans('Family') }}</h2>
        <p class="text-xs text-gray-500">{{ trans('Choose a family to preview') }}</p>
      </div>
    </template>

    <div class="mx-auto">
      <ul class="space-y-3">
        <li v-for="(family, index) in props.data.family.data" :key="family.slug" @click="() => onChangeFamily(family)"
          class="border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow" :class="[
            'rounded-lg shadow-sm transition-shadow',
            family.slug == dataPicked.sub_department?.slug
              ? 'border border-blue-500 ring-2 ring-blue-300 shadow-md'
              : 'border border-gray-200 hover:shadow-md hover:border-gray-300'
          ]">
          <div class="flex items-center justify-between px-4 py-3 cursor-pointer group hover:bg-gray-50 rounded-t-lg">
            <div class="flex items-center gap-3 text-gray-800 font-medium">
              <FontAwesomeIcon :icon="faDotCircle" class="w-4 h-4" :class="family?.slug == dataPicked?.sub_department?.slug
                ? 'text-blue-500'
                : 'text-gray-400'
                " />

              <span class="group-hover:underline">
                {{ family.name }}
              </span>
            </div>
          </div>
        </li>
      </ul>
    </div>
  </Drawer>

</template>

<style>
.block-active {
  border: 2px solid color-mix(in srgb, v-bind(themeColor4) 80%, black);
}

.background-primary {
    background-color: v-bind(themeColor4);
}

.border-primary {
    border-color: v-bind(themeColor4);
}

.text-primary {
    color: v-bind(themeColor4) !important;
}

.primaryLink {
    background: linear-gradient(
        to top,
        v-bind(themeColor4),
        v-bind(themeColor4)
    );

    @apply focus:ring-0
    focus:outline-none
    focus:border-none
    bg-no-repeat
    [background-position:0%_100%]
    [background-size:100%_0.2em]
    motion-safe:transition-all
    motion-safe:duration-200
    hover:[background-size:100%_100%]
    focus:[background-size:100%_100%]
    px-1
    py-0.5;

    &:hover,
    &:focus {
        color: #374151;
    }
}

</style>