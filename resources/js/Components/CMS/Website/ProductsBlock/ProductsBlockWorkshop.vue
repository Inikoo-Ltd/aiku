<script setup lang="ts">
import { ref, provide, toRaw, watch, computed, inject, reactive, onMounted } from "vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import {
  faCube,
  faLink,
  faStar,
  faCircle,
  faChevronLeft,
  faChevronRight,
  faDesktop,
  faThLarge,
  faPaintBrushAlt,
  faMedal,
} from "@fas"
import { router } from "@inertiajs/vue3"
import { debounce, cloneDeep } from "lodash-es"
import { notify } from "@kyvg/vue3-notification"
import ToggleSwitch from "primevue/toggleswitch"

import { getComponent } from "@/Composables/getWorkshopComponents"
import { routeType } from "@/types/route"
import { setColorStyleRootByEl } from "@/Composables/useApp"

import SideMenuFamilyWorkshop from "./SideMenuProductsWorkshop.vue"
import EmptyState from "@/Components/Utils/EmptyState.vue"
import ScreenView from "@/Components/ScreenView.vue"

import "@/../css/Iris/editor.css"

library.add(
  faCube,
  faLink,
  faStar,
  faCircle,
  faChevronLeft,
  faChevronRight,
  faDesktop,
  faThLarge,
  faPaintBrushAlt,
  faMedal
)

interface LayoutPayload {
  code?: string
  data?: {
    fieldValue?: Record<string, any>
  }
}

interface TemplatePayload extends LayoutPayload {}

interface FamilyData {
  id: number | string
  slug: string
  [key: string]: any
}


const props = defineProps<{
  data: {
    web_block_types: any
    autosaveRoute: routeType
    layout: LayoutPayload
    products: any[]
    top_seller: any[]
    families: FamilyData[]
    family: FamilyData
  }
  currency: {
    code: string
    name: string
  }
  layout_theme: {
    color: string[]
  }
}>()


const parentLayout = inject<any>("layout")

const rootRef = ref<HTMLElement | null>(null)

const layout = reactive(cloneDeep(parentLayout))
layout.app.theme = props.layout_theme.color
layout.iris = {
  ...layout.iris,
  is_logged_in: true,
}

provide("layout", layout)


const selectedTab = ref(props.data.layout?.data ? 1 : 0)
const isSaving = ref(false)
const currentView = ref<"desktop" | "tablet" | "mobile">("desktop")
const iframeClass = ref("w-full h-full")

provide("currentView", currentView)


const tabs = [
  { label: "Templates", icon: faThLarge, tooltip: "template" },
  { label: "Settings", icon: faPaintBrushAlt, tooltip: "setting" },
  { label: "Bestseller", icon: faMedal, tooltip: "bestseller" },
]

const availableTabs = computed(() =>
  props.data.layout?.data ? tabs : [tabs[0]]
)


const previewData = computed(() => ({
  ...props.data.layout?.data?.fieldValue,
  products:
    selectedTab.value === 2
      ? props.data.top_seller
      : props.data.products,
  model_type: "family",
  model_id: props.data.family.id,
  model_slug: props.data.family.slug,
}))


const saveLayout = () => {
  const payload = toRaw(props.data.layout)

  delete payload?.data?.fieldValue?.layout
  delete payload?.data?.fieldValue?.sub_departments

  router.patch(
    route(props.data.autosaveRoute.name, props.data.autosaveRoute.parameters),
    { layout: payload },
    {
      onStart: () => (isSaving.value = true),
      onFinish: () => (isSaving.value = false),
      onError: (errors) => {
        notify({
          title: "Autosave Failed",
          text: errors?.message || "Unknown error occurred",
          type: "error",
        })
      },
    }
  )
}

const autosave = debounce(saveLayout, 800)

const pickTemplate = (template: TemplatePayload) => {
  props.data.layout = {
    ...template,
    data: {
      fieldValue: {
        container: { properties: null },
      },
      ...template.data,
    },
  }

  autosave()
}

const resolveIframeClass = (view: typeof currentView.value) => {
  if (view === "mobile") return "w-[375px] h-[667px] mx-auto"
  if (view === "tablet") return "w-[768px] h-[1024px] mx-auto"
  return "w-full h-full"
}

watch(currentView, (view) => {
  iframeClass.value = resolveIframeClass(view)
})


onMounted(() => {
  if (rootRef.value && props.layout_theme?.color) {
    setColorStyleRootByEl(rootRef.value, props.layout_theme.color)
  }
})
</script>

<template>
  <div class="h-[85vh] grid grid-cols-12 gap-4 p-3">
    <!-- SIDEBAR -->
    <aside class="col-span-3 bg-white rounded-xl shadow-md p-4 overflow-y-auto border">
      <SideMenuFamilyWorkshop
        :data="data.layout"
        :webBlockTypes="data.web_block_types"
        :dataList="data.families"
        :tabs="availableTabs"
        v-model:selectedTab="selectedTab"
        @auto-save="autosave"
        @set-up-template="pickTemplate"
      />
    </aside>

    <!-- PREVIEW -->
    <section class="col-span-9 bg-white rounded-xl shadow-md flex flex-col border overflow-hidden">
      <!-- HEADER -->
      <header class="flex justify-between items-center px-4 py-2 bg-gray-100 border-b shrink-0">
        <div class="hidden lg:block">
          <ScreenView v-model="currentView" />
        </div>
        <div class="flex items-center gap-3">
          <span class="text-sm font-medium">Login</span>
          <ToggleSwitch v-model="layout.iris.is_logged_in" />
        </div>
      </header>

      <!-- CONTENT -->
      <div class="flex-1 overflow-auto">
        <div v-if="data.layout?.code" class="editor-class">
          <div
            ref="rootRef"
            :class="['border-2 border-t-0 overflow-auto', iframeClass]"
          >
            <component
              class="flex-1 overflow-auto active-block"
              :is="getComponent(data.layout.code, { shop_type: layout?.shopState?.type })"
              :code="data.layout.code"
              :screenType="currentView"
              :modelValue="previewData"
            />
          </div>
        </div>

        <EmptyState v-else />
      </div>
    </section>
  </div>
</template>
