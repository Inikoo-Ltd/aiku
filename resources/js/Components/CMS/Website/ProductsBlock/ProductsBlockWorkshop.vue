<script setup lang="ts">
import { ref, provide, toRaw, watch, computed } from "vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCube, faLink, faStar, faCircle, faChevronLeft, faChevronRight, faDesktop, faThLarge, faPaintBrushAlt, faMedal } from "@fas"
import { router } from "@inertiajs/vue3"
import { debounce } from "lodash-es"
import { notify } from "@kyvg/vue3-notification"

import { getComponent } from "@/Composables/getWorkshopComponents"
import { routeType } from "@/types/route"

import SideMenuFamilyWorkshop from "./SideMenuProductsWorkshop.vue"
import EmptyState from "@/Components/Utils/EmptyState.vue"
import ScreenView from "@/Components/ScreenView.vue"

import "@/../css/Iris/editor.css"

library.add(faCube, faLink, faStar, faCircle, faChevronLeft, faChevronRight, faDesktop, faMedal)


interface LayoutData {
  code?: string
  data?: {
    fieldValue?: Record<string, any>
  }
}

interface TemplateData {
  code?: string
  data?: {
    fieldValue?: Record<string, any>
  }
}

interface FamilyData {
  id: number | string
  slug: string
  [key: string]: any
}

interface Props {
  data: {
    web_block_types: any
    autosaveRoute: routeType
    layout: LayoutData
    family: FamilyData
    families?: FamilyData[]
    products: any[]
    top_seller: any[]
  }
}

const props = defineProps<Props>()

const selectedTab = ref(props.data.layout.data ? 1 : 0)
const isLoadingSave = ref(false)
const iframeClass = ref("w-full h-full")
const currentView = ref<"desktop" | "tablet" | "mobile">("desktop")

provide("currentView", currentView)

/* -------------------------------
   📘 Tabs Configuration
---------------------------------- */
const tabs = [
  { label: "Templates", icon: faThLarge, tooltip: "template" },
  { label: "Settings", icon: faPaintBrushAlt, tooltip: "setting" },
  { label: "Bestseller", icon: faMedal, tooltip: "bestseller" },
]

/* -------------------------------
   📘 Functions
---------------------------------- */
const setIframeView = (view: string) => {
  switch (view) {
    case "mobile":
      return "w-[375px] h-[667px] mx-auto"
    case "tablet":
      return "w-[768px] h-[1024px] mx-auto"
    default:
      return "w-full h-full"
  }
}

const doAutosave = () => {
  const payload = toRaw(props.data.layout)
  delete payload.data?.fieldValue?.layout
  delete payload.data?.fieldValue?.sub_departments

  router.patch(
    route(props.data.autosaveRoute.name, props.data.autosaveRoute.parameters),
    { layout: payload },
    {
      onStart: () => (isLoadingSave.value = true),
      onFinish: () => (isLoadingSave.value = false),
      onError: (errors) => {
        notify({
          title: "Autosave Failed",
          text: errors?.message || "Unknown error occurred.",
          type: "error",
        })
      },
    }
  )
}

const autosave = debounce(doAutosave, 800)

const onPickTemplate = (template: TemplateData) => {
  props.data.layout = {
    ...template,
    data: {
      ...template.data,
      fieldValue: {
        container: {
          properties: null,
        },
      },
    },
  }
  autosave()
}

const onChangeDepartment = (value: Record<string, any>) => {
  const newDepartment = { ...value }
  delete newDepartment.sub_departments

  if (props.data.layout?.data?.fieldValue) {
    props.data.layout.data.fieldValue.layout = value
    props.data.layout.data.fieldValue.sub_departments = value.sub_departments || []
  }
  autosave()
}

const computedTabs = computed(() => {
  return props.data.layout.data ? tabs : [tabs[0]]
})

const computedDataProduct = computed(() => ({
  ...props.data.layout.data?.fieldValue,
  products: selectedTab.value !== 2 ?  props.data.products : props.data.top_seller,
  model_type: "family",
  model_id: props.data.family.id,
  model_slug: props.data.family.slug,
}))

watch(currentView, (newValue) => {
  iframeClass.value = setIframeView(newValue)
})
</script>

<template>
  <div class="h-[85vh] grid grid-cols-12 gap-4 p-3">
    <div class="col-span-3 bg-white rounded-xl shadow-md p-4 overflow-y-auto border">
      <SideMenuFamilyWorkshop
        :data="data.layout"
        :webBlockTypes="data.web_block_types"
        :dataList="data.families"
        v-model:selectedTab="selectedTab"
        :tabs="computedTabs"
        @auto-save="autosave"
        @set-up-template="onPickTemplate"
        @onChangeDepartment="onChangeDepartment"
        @update:selectedTab="(e) => (selectedTab = e)"
      />
    </div>

    <div class="col-span-9 bg-white rounded-xl shadow-md flex flex-col overflow-auto border">
      <div class="flex justify-between items-center px-4 py-2 bg-gray-100 border-b">
        <div class="py-1 px-2 cursor-pointer lg:block hidden" v-tooltip="'Desktop view'">
          <ScreenView @screenView="(e) => (currentView = e)" v-model="currentView" />
        </div>
      </div>

      <div v-if="data.layout?.code" class="editor-class">
        <div :class="['border-2 border-t-0 overflow-auto', iframeClass]">
          <component
            :screenType="currentView"
            class="flex-1 overflow-auto active-block"
            :is="getComponent(data.layout.code)"
            :modelValue="computedDataProduct"
          />
        </div>
      </div>

      <div v-else>
        <EmptyState />
      </div>
    </div>
  </div>
</template>
